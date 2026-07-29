<?php

namespace App\Enums;

enum Action: string
{
    /** See a record. On personal modules this means one's own records only. */
    case View = 'view';
    /** See other people's records in the module, not just one's own. */
    case ViewAll = 'view-all';
    case Create = 'create';
    case Edit = 'edit';
    case Delete = 'delete';
    /** Give work to, or grant access to, somebody else. */
    case Assign = 'assign';
    /** Decide on a request: leave, expenses. Distinct from editing it. */
    case Approve = 'approve';
    case Comment = 'comment';
    case Upload = 'upload';
    case Export = 'export';
    /** Run a process that produces records, such as a monthly payroll. */
    case Generate = 'generate';
    /** Record money actually leaving the business. */
    case Pay = 'pay';
    /** Retrieve a generated document, such as a payslip PDF. */
    case Download = 'download';
}
