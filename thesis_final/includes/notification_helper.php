<?php
/**
 * FIXED Notification Helper - Complete Working Version
 * File: thesis_final/includes/notification_helper.php
 * WITH EMAIL INTEGRATION
 */

class NotificationHelper {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Send notification to a single user
     */
    public function sendToUser($user_id, $title, $message, $type = 'general') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $result = $stmt->execute([$user_id, $title, $message, $type]);
            
            if ($result) {
                error_log("✅ Notification created: User $user_id - $title");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("❌ Notification Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification to multiple users
     */
    public function sendToMultipleUsers($user_ids, $title, $message, $type = 'general') {
        $success_count = 0;
        foreach ($user_ids as $user_id) {
            if ($this->sendToUser($user_id, $title, $message, $type)) {
                $success_count++;
            }
        }
        error_log("✅ Sent $success_count notifications to " . count($user_ids) . " users");
        return $success_count;
    }
    
    /**
     * Send notification to all group members
     */
    public function sendToGroup($group_id, $title, $message, $type = 'general', $exclude_user_id = null) {
        try {
            $sql = "SELECT user_id FROM group_members WHERE group_id = ?";
            $params = [$group_id];
            
            if ($exclude_user_id) {
                $sql .= " AND user_id != ?";
                $params[] = $exclude_user_id;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($members)) {
                return $this->sendToMultipleUsers($members, $title, $message, $type);
            }
            
            return 0;
        } catch (PDOException $e) {
            error_log("❌ Group Notification Error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Send notification to all admins
     */
    public function sendToAllAdmins($title, $message, $type = 'general') {
        try {
            $stmt = $this->pdo->prepare("
                SELECT user_id FROM users 
                WHERE role = 'admin' AND status = 'active'
            ");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($admins)) {
                return $this->sendToMultipleUsers($admins, $title, $message, $type);
            }
            
            return 0;
        } catch (PDOException $e) {
            error_log("❌ Admin Notification Error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Notify when new user is created
     */
    public function notifyNewUser($user_id, $role) {
        $messages = [
            'student' => 'Welcome to the Thesis Panel System! Create your thesis group to get started.',
            'panelist' => 'Welcome! You will receive panel assignment notifications here.',
            'admin' => 'Welcome to the admin portal. You can now manage the system.'
        ];
        
        $result = $this->sendToUser(
            $user_id, 
            'Welcome to Thesis Panel System!', 
            $messages[$role] ?? 'Your account has been created successfully.', 
            'general'
        );
        
        error_log("✅ New user notification: User $user_id ($role) - " . ($result ? "SUCCESS" : "FAILED"));
        return $result;
    }
    
    /**
     * Notify when thesis group is created
     */
    public function notifyGroupCreated($group_id, $group_name, $creator_id) {
        // Notify all admins
        $admin_result = $this->sendToAllAdmins(
            'New Thesis Group Created',
            "A new thesis group '$group_name' has been created and is awaiting approval.",
            'general'
        );
        
        // Notify group members
        $member_result = $this->sendToGroup(
            $group_id,
            'Thesis Group Created',
            "You have been added to thesis group: $group_name. Waiting for admin approval.",
            'general'
        );
        
        error_log("✅ Group creation notifications: Admins=$admin_result, Members=$member_result");
        return $admin_result + $member_result;
    }
    
    /**
     * Notify when thesis group status changes
     */
    public function notifyGroupStatusChange($group_id, $status, $reason = null) {
        $titles = [
            'approved' => 'Thesis Approved!',
            'rejected' => 'Thesis Needs Revision'
        ];
        
        $messages = [
            'approved' => 'Congratulations! Your thesis has been approved. You can now request a defense schedule.',
            'rejected' => 'Your thesis needs revision. ' . ($reason ? "Reason: $reason" : 'Please check the feedback.')
        ];
        
        $result = $this->sendToGroup(
            $group_id,
            $titles[$status] ?? 'Thesis Status Updated',
            $messages[$status] ?? 'Your thesis status has been updated.',
            'general'
        );
        
        error_log("✅ Group status change notification: Group $group_id - Status=$status - Sent to $result members");
        return $result;
    }
    
    /**
     * Notify when schedule is requested
     */
    public function notifyScheduleRequested($group_id, $schedule_date, $schedule_time) {
        $date_formatted = date('F d, Y', strtotime($schedule_date));
        $time_formatted = date('h:i A', strtotime($schedule_time));
        
        // Notify admins
        $admin_result = $this->sendToAllAdmins(
            'New Schedule Request',
            "A thesis group has requested a defense schedule for $date_formatted at $time_formatted.",
            'schedule'
        );
        
        // Notify group members
        $member_result = $this->sendToGroup(
            $group_id,
            'Schedule Request Submitted',
            "Your defense schedule request for $date_formatted at $time_formatted has been submitted.",
            'schedule'
        );
        
        error_log("✅ Schedule request notifications: Admins=$admin_result, Members=$member_result");
        return $admin_result + $member_result;
    }
    
    /**
     * Notify when schedule status changes
     */
    public function notifyScheduleStatusChange($group_id, $status, $schedule_date, $schedule_time, $notes = null) {
        $date_formatted = date('F d, Y', strtotime($schedule_date));
        $time_formatted = date('h:i A', strtotime($schedule_time));
        
        if ($status === 'approved') {
            $result = $this->sendToGroup(
                $group_id,
                'Defense Schedule Approved!',
                "Your defense has been scheduled for $date_formatted at $time_formatted.",
                'schedule'
            );
        } else {
            $message = "Your schedule request for $date_formatted at $time_formatted was not approved.";
            if ($notes) {
                $message .= " Reason: $notes";
            }
            $result = $this->sendToGroup(
                $group_id,
                'Schedule Request Declined',
                $message,
                'schedule'
            );
        }
        
        error_log("✅ Schedule status change notification: Group $group_id - Status=$status - Sent to $result members");
        return $result;
    }
    
    /**
     * Notify when panelist is assigned
     */
    public function notifyPanelistAssigned($panelist_id, $schedule_id, $role) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ds.defense_date, ds.defense_time, tg.group_name 
                FROM defense_schedules ds
                LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id
                WHERE ds.schedule_id = ?
            ");
            $stmt->execute([$schedule_id]);
            $schedule = $stmt->fetch();
            
            if ($schedule) {
                $date_formatted = date('F d, Y', strtotime($schedule['defense_date']));
                $time_formatted = date('h:i A', strtotime($schedule['defense_time']));
                
                $result = $this->sendToUser(
                    $panelist_id,
                    'New Panel Assignment',
                    "You have been assigned as a " . ucfirst($role) . " for {$schedule['group_name']} on $date_formatted at $time_formatted.",
                    'assignment'
                );
                
                error_log("✅ Panelist assignment notification: Panelist $panelist_id - Schedule $schedule_id - " . ($result ? "SUCCESS" : "FAILED"));
                return $result;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("❌ Panelist Assignment Notification Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify when evaluation is submitted
     */
    public function notifyEvaluationSubmitted($group_id, $panelist_name) {
        $result = $this->sendToGroup(
            $group_id,
            'Evaluation Submitted',
            "Panelist $panelist_name has submitted their evaluation for your thesis defense.",
            'evaluation'
        );
        
        error_log("✅ Evaluation submission notification: Group $group_id - By $panelist_name - Sent to $result members");
        return $result;
    }
    
    /**
     * Notify when all evaluations are complete
     */
    public function notifyAllEvaluationsComplete($group_id) {
        $result = $this->sendToGroup(
            $group_id,
            'All Evaluations Complete',
            'All panelists have submitted their evaluations. You can now view your results.',
            'evaluation'
        );
        
        error_log("✅ All evaluations complete notification: Group $group_id - Sent to $result members");
        return $result;
    }
    
    /**
     * Notify when document is uploaded
     */
    public function notifyDocumentUploaded($group_id, $uploader_name, $document_type, $exclude_user_id) {
        $result = $this->sendToGroup(
            $group_id,
            'New Document Uploaded',
            "$uploader_name uploaded a new document: $document_type",
            'document',
            $exclude_user_id
        );
        
        error_log("✅ Document upload notification: Group $group_id - By $uploader_name - Type=$document_type - Sent to $result members");
        return $result;
    }
    
    // ================================================================
    // 📧 EMAIL NOTIFICATION METHODS - NEW ADDITIONS
    // ================================================================
    
    /**
     * Send email when thesis group status changes
     */
    public function emailGroupStatusChange($group_id, $status, $reason = null) {
        require_once __DIR__ . '/email_config.php';
        require_once __DIR__ . '/email_templates.php';
        
        try {
            // Get group and member details
            $stmt = $this->pdo->prepare("
                SELECT tg.group_name, tg.thesis_title, u.name, u.email
                FROM thesis_groups tg
                INNER JOIN group_members gm ON tg.group_id = gm.group_id
                INNER JOIN users u ON gm.user_id = u.user_id
                WHERE tg.group_id = ?
            ");
            $stmt->execute([$group_id]);
            $members = $stmt->fetchAll();
            
            if (empty($members)) {
                error_log("⚠️ No members found for group $group_id");
                return 0;
            }
            
            $group_name = $members[0]['group_name'];
            $thesis_title = $members[0]['thesis_title'];
            
            $sent_count = 0;
            foreach ($members as $member) {
                if ($status === 'approved') {
                    $email_body = getGroupApprovedEmail($group_name, $thesis_title);
                    $subject = "✅ Thesis Group Approved - {$group_name}";
                } else {
                    $email_body = getGroupRejectedEmail($group_name, $thesis_title, $reason ?? 'Please review feedback');
                    $subject = "⚠️ Thesis Group Needs Revision - {$group_name}";
                }
                
                if (sendEmail($member['email'], $member['name'], $subject, $email_body)) {
                    $sent_count++;
                }
            }
            
            error_log("✅ Sent {$sent_count} emails for group status: {$status}");
            return $sent_count;
        } catch (PDOException $e) {
            error_log("❌ Email Group Status Error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Send email when schedule status changes
     */
    public function emailScheduleStatusChange($group_id, $status, $schedule_date, $schedule_time, $venue_name, $notes = null) {
        require_once __DIR__ . '/email_config.php';
        require_once __DIR__ . '/email_templates.php';
        
        try {
            // Get group members
            $stmt = $this->pdo->prepare("
                SELECT tg.group_name, u.name, u.email
                FROM thesis_groups tg
                INNER JOIN group_members gm ON tg.group_id = gm.group_id
                INNER JOIN users u ON gm.user_id = u.user_id
                WHERE tg.group_id = ?
            ");
            $stmt->execute([$group_id]);
            $members = $stmt->fetchAll();
            
            if (empty($members)) {
                error_log("⚠️ No members found for group $group_id");
                return 0;
            }
            
            $group_name = $members[0]['group_name'];
            
            $sent_count = 0;
            foreach ($members as $member) {
                if ($status === 'approved') {
                    $email_body = getScheduleApprovedEmail($group_name, $schedule_date, $schedule_time, $venue_name);
                    $subject = "📅 Defense Schedule Confirmed - {$group_name}";
                } else {
                    $email_body = getScheduleRejectedEmail($group_name, $schedule_date, $schedule_time, $notes ?? 'Please choose another slot');
                    $subject = "❌ Schedule Request Not Approved - {$group_name}";
                }
                
                if (sendEmail($member['email'], $member['name'], $subject, $email_body)) {
                    $sent_count++;
                }
            }
            
            error_log("✅ Sent {$sent_count} emails for schedule status: {$status}");
            return $sent_count;
        } catch (PDOException $e) {
            error_log("❌ Email Schedule Status Error: " . $e->getMessage());
            return 0;
        }
    }
}