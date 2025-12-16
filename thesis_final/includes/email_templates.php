<?php
/**
 * Email Templates
 * File: thesis_final/includes/email_templates.php
 */

/**
 * Thesis Group Approved Email
 */
function getGroupApprovedEmail($group_name, $thesis_title) {
    return "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <div style='background: linear-gradient(135deg, #DC143C 0%, #B01030 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: #FFFFF0; margin: 0;'> Thesis Group Approved!</h1>
            </div>
            
            <div style='padding: 30px; background: #FFFFF0;'>
                <p style='font-size: 16px;'>Great news!</p>
                
                <p style='font-size: 16px;'>Your thesis group has been <strong style='color: #10b981;'>APPROVED</strong> by the administrator.</p>
                
                <div style='background: #e0f2fe; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>Group Name:</strong> {$group_name}</p>
                    <p style='margin: 5px 0;'><strong>Thesis Title:</strong> {$thesis_title}</p>
                </div>
                
                <h3 style='color: #DC143C;'>Next Steps:</h3>
                <ul style='font-size: 15px;'>
                    <li>Upload your thesis documents</li>
                    <li>Request a defense schedule when ready</li>
                    <li>Prepare your presentation materials</li>
                </ul>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='https://yourwebsite.com/student/dashboard.php' 
                       style='background: #DC143C; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                        Go to Dashboard
                    </a>
                </div>
            </div>
            
            <div style='text-align: center; padding: 20px; color: #666; font-size: 14px; background: #f5f5f5; border-radius: 0 0 10px 10px;'>
                <p style='margin: 0;'>Thesis Panel Scheduling System</p>
                <p style='margin: 5px 0;'>This is an automated email. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Thesis Group Rejected Email
 */
function getGroupRejectedEmail($group_name, $thesis_title, $rejection_reason) {
    return "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <div style='background: linear-gradient(135deg, #DC143C 0%, #B01030 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: #FFFFF0; margin: 0;'> Thesis Group Needs Revision</h1>
            </div>
            
            <div style='padding: 30px; background: #FFFFF0;'>
                <p style='font-size: 16px;'>Your thesis group requires revisions before approval.</p>
                
                <div style='background: #fee2e2; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #DC143C;'>
                    <p style='margin: 5px 0;'><strong>Group Name:</strong> {$group_name}</p>
                    <p style='margin: 5px 0;'><strong>Thesis Title:</strong> {$thesis_title}</p>
                </div>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='color: #856404; margin-top: 0;'>Feedback from Administrator:</h3>
                    <p style='margin: 0; white-space: pre-wrap;'>{$rejection_reason}</p>
                </div>
                
                <h3 style='color: #DC143C;'>What to do next:</h3>
                <ul style='font-size: 15px;'>
                    <li>Review the feedback carefully</li>
                    <li>Address all concerns raised</li>
                    <li>Update your thesis information</li>
                    <li>Resubmit for approval</li>
                </ul>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='https://yourwebsite.com/student/thesis-group.php' 
                       style='background: #DC143C; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                        Update Thesis Group
                    </a>
                </div>
            </div>
            
            <div style='text-align: center; padding: 20px; color: #666; font-size: 14px; background: #f5f5f5; border-radius: 0 0 10px 10px;'>
                <p style='margin: 0;'>Thesis Panel Scheduling System</p>
                <p style='margin: 5px 0;'>This is an automated email. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Schedule Request Approved Email
 */
function getScheduleApprovedEmail($group_name, $defense_date, $defense_time, $venue_name) {
    $formatted_date = date('l, F d, Y', strtotime($defense_date));
    $formatted_time = date('h:i A', strtotime($defense_time));
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <div style='background: linear-gradient(135deg, #DC143C 0%, #B01030 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: #FFFFF0; margin: 0;'>📅 Defense Schedule Confirmed!</h1>
            </div>
            
            <div style='padding: 30px; background: #FFFFF0;'>
                <p style='font-size: 16px;'>Congratulations!</p>
                
                <p style='font-size: 16px;'>Your defense schedule has been <strong style='color: #10b981;'>APPROVED</strong>.</p>
                
                <div style='background: #e0f2fe; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3b82f6;'>
                    <h3 style='margin-top: 0; color: #1e40af;'>📆 Defense Details:</h3>
                    <p style='margin: 10px 0; font-size: 16px;'><strong>Group:</strong> {$group_name}</p>
                    <p style='margin: 10px 0; font-size: 16px;'><strong>Date:</strong> {$formatted_date}</p>
                    <p style='margin: 10px 0; font-size: 16px;'><strong>Time:</strong> {$formatted_time}</p>
                    <p style='margin: 10px 0; font-size: 16px;'><strong>Venue:</strong> {$venue_name}</p>
                </div>
                
                <h3 style='color: #DC143C;'>Prepare for your defense:</h3>
                <ul style='font-size: 15px;'>
                    <li>Finalize your presentation</li>
                    <li>Review your thesis manuscript</li>
                    <li>Prepare for panel questions</li>
                    <li>Arrive 15 minutes early</li>
                </ul>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='https://yourwebsite.com/student/schedule.php' 
                       style='background: #DC143C; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                        View Schedule Details
                    </a>
                </div>
            </div>
            
            <div style='text-align: center; padding: 20px; color: #666; font-size: 14px; background: #f5f5f5; border-radius: 0 0 10px 10px;'>
                <p style='margin: 0;'>Thesis Panel Scheduling System</p>
                <p style='margin: 5px 0;'>This is an automated email. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Schedule Request Rejected Email
 */
function getScheduleRejectedEmail($group_name, $defense_date, $defense_time, $notes) {
    $formatted_date = date('F d, Y', strtotime($defense_date));
    $formatted_time = date('h:i A', strtotime($defense_time));
    
    return "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <div style='background: linear-gradient(135deg, #DC143C 0%, #B01030 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: #FFFFF0; margin: 0;'> Schedule Request Not Approved</h1>
            </div>
            
            <div style='padding: 30px; background: #FFFFF0;'>
                <p style='font-size: 16px;'>Your schedule request was not approved.</p>
                
                <div style='background: #fee2e2; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>Group:</strong> {$group_name}</p>
                    <p style='margin: 5px 0;'><strong>Requested Date:</strong> {$formatted_date}</p>
                    <p style='margin: 5px 0;'><strong>Requested Time:</strong> {$formatted_time}</p>
                </div>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='color: #856404; margin-top: 0;'>Reason:</h3>
                    <p style='margin: 0; white-space: pre-wrap;'>{$notes}</p>
                </div>
                
                <h3 style='color: #DC143C;'>Next Steps:</h3>
                <ul style='font-size: 15px;'>
                    <li>Review the administrator's feedback</li>
                    <li>Choose a different time slot</li>
                    <li>Submit a new schedule request</li>
                </ul>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='https://yourwebsite.com/student/schedule.php' 
                       style='background: #DC143C; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                        Request New Schedule
                    </a>
                </div>
            </div>
            
            <div style='text-align: center; padding: 20px; color: #666; font-size: 14px; background: #f5f5f5; border-radius: 0 0 10px 10px;'>
                <p style='margin: 0;'>Thesis Panel Scheduling System</p>
                <p style='margin: 5px 0;'>This is an automated email. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Panelist Assignment Email Template
 * ADD THIS to thesis_final/includes/email_templates.php
 */

function getPanelistAssignmentEmail($panelist_name, $group_name, $thesis_title, $defense_date, $defense_time, $venue_name, $role) {
    $formatted_date = date('l, F d, Y', strtotime($defense_date));
    $formatted_time = date('h:i A', strtotime($defense_time));
    $role_display = ucfirst($role);
    $role_color = $role === 'chair' ? '#3b82f6' : '#10b981';
    
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
            .email-wrapper { background: #f5f5f5; padding: 40px 20px; }
            .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .email-header { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 40px 30px; text-align: center; }
            .email-header h1 { color: white; margin: 0; font-size: 28px; font-weight: 700; }
            .email-header .icon { font-size: 60px; margin-bottom: 15px; }
            .email-body { padding: 40px 30px; }
            .email-body p { color: #353535; line-height: 1.8; margin: 0 0 20px; font-size: 16px; }
            .assignment-card { background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); border-left: 4px solid {$role_color}; padding: 25px; border-radius: 12px; margin: 25px 0; }
            .assignment-card h3 { color: #4338ca; margin: 0 0 20px; font-size: 20px; }
            .assignment-item { display: flex; align-items: flex-start; margin: 15px 0; }
            .assignment-item .icon { font-size: 24px; margin-right: 15px; min-width: 30px; }
            .assignment-item .content { flex: 1; }
            .assignment-item .label { color: #4338ca; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
            .assignment-item .value { color: #3730a3; font-size: 16px; font-weight: 600; margin-top: 5px; }
            .role-badge { display: inline-block; background: {$role_color}; color: white; padding: 10px 25px; border-radius: 20px; font-weight: 700; font-size: 16px; margin: 15px 0; }
            .thesis-info { background: #f0fdf4; border-left: 4px solid #10b981; padding: 20px; border-radius: 8px; margin: 25px 0; }
            .thesis-info h3 { color: #065f46; margin: 0 0 15px; font-size: 18px; }
            .thesis-info p { color: #047857; margin: 8px 0; line-height: 1.6; }
            .action-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 8px; margin: 25px 0; }
            .action-box h3 { color: #92400e; margin: 0 0 15px; font-size: 18px; }
            .action-box ul { margin: 0; padding-left: 20px; }
            .action-box li { color: #78350f; margin: 10px 0; line-height: 1.6; }
            .cta-button { display: inline-block; background: #6366f1; color: white !important; padding: 16px 40px; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 20px 0; transition: all 0.3s; }
            .email-footer { background: #f5f5f5; padding: 30px; text-align: center; color: #6b6b6b; font-size: 14px; border-top: 1px solid #e0e0e0; }
            .divider { height: 1px; background: linear-gradient(to right, transparent, #e0e0e0, transparent); margin: 30px 0; }
        </style>
    </head>
    <body>
        <div class='email-wrapper'>
            <div class='email-container'>
                <div class='email-header'>
                    <div class='icon'>👨‍🏫</div>
                    <h1>New Panel Assignment</h1>
                </div>
                
                <div class='email-body'>
                    <p style='font-size: 18px; font-weight: 600; color: #6366f1;'>Hello, {$panelist_name}!</p>
                    
                    <p>You have been assigned as a panelist for an upcoming thesis defense. Please review the details below and respond to this assignment.</p>
                    
                    <div style='text-align: center; margin: 25px 0;'>
                        <span class='role-badge'>Your Role: {$role_display}</span>
                    </div>
                    
                    <div class='assignment-card'>
                        <h3>📅 Defense Schedule</h3>
                        
                        <div class='assignment-item'>
                            <div class='icon'>📅</div>
                            <div class='content'>
                                <div class='label'>Date</div>
                                <div class='value'>{$formatted_date}</div>
                            </div>
                        </div>
                        
                        <div class='assignment-item'>
                            <div class='icon'>🕒</div>
                            <div class='content'>
                                <div class='label'>Time</div>
                                <div class='value'>{$formatted_time}</div>
                            </div>
                        </div>
                        
                        <div class='assignment-item'>
                            <div class='icon'>📍</div>
                            <div class='content'>
                                <div class='label'>Venue</div>
                                <div class='value'>{$venue_name}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class='thesis-info'>
                        <h3>📚 Thesis Information</h3>
                        <p><strong>Group:</strong> {$group_name}</p>
                        <p><strong>Title:</strong> {$thesis_title}</p>
                    </div>
                    
                    <div class='action-box'>
                        <h3>✅ Next Steps:</h3>
                        <ul>
                            <li><strong>Review Assignment:</strong> Check if the date and time work for your schedule</li>
                            <li><strong>Respond Promptly:</strong> Accept or decline the assignment as soon as possible</li>
                            <li><strong>Review Documents:</strong> Access thesis documents after accepting</li>
                            <li><strong>Prepare Questions:</strong> Review the thesis and prepare evaluation criteria</li>
                        </ul>
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='https://yourwebsite.com/panelist/assignments.php' class='cta-button'>
                            View Assignment Details →
                        </a>
                    </div>
                    
                    <div class='divider'></div>
                    
                    <p style='font-size: 14px; color: #6b6b6b; background: #fee2e2; padding: 15px; border-radius: 8px; border-left: 4px solid #DC143C;'>
                        <strong>⚠️ Important:</strong> Please respond to this assignment within 48 hours. If you cannot attend, decline the assignment so another panelist can be assigned.
                    </p>
                </div>
                
                <div class='email-footer'>
                    <p><strong>Thesis Panel Scheduling System</strong></p>
                    <p>This is an automated email. Please do not reply directly to this message.</p>
                    <p style='margin-top: 15px; font-size: 12px;'>© 2025 Your University. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>