<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attach the payslip PDF to the email
    |--------------------------------------------------------------------------
    |
    | Attaching is what most people expect, and it is the default.
    |
    | It does mean net pay, deductions and tax figures sit in an inbox and pass
    | through whatever mail relays are between here and there, usually
    | unencrypted and usually retained. Where that is not acceptable, turn this
    | off: the email then says a payslip is ready and links to it, and the file
    | is only ever served over an authenticated request to someone the record
    | scope allows.
    |
    */

    'attach_pdf' => env('PAYSLIP_ATTACH_PDF', true),

];
