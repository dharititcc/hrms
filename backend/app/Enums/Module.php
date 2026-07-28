<?php

namespace App\Enums;

/**
 * The resources permissions apply to. A permission is always module.action,
 * so "edit" never means "edit anything" — it is scoped to one module.
 */
enum Module: string
{
    case Staff = 'staff';
    case Attendance = 'attendance';
    case Leave = 'leave';
    case Payroll = 'payroll';
    case Expenses = 'expenses';
    case Tasks = 'tasks';
    case Meetings = 'meetings';
    case Projects = 'projects';
    case Recruitment = 'recruitment';
    case Performance = 'performance';
    case Assets = 'assets';
    case Announcements = 'announcements';
    case Reports = 'reports';
    case Attachments = 'attachments';
    case Activity = 'activity';
}
