<?php

/*
|--------------------------------------------------------------------------
| Payroll country rules
|--------------------------------------------------------------------------
|
| IMPORTANT — READ BEFORE RUNNING REAL PAYROLL
|
| The rates below are STARTING DEFAULTS, not verified legal advice. Statutory
| rates, thresholds, caps and exemptions change every tax year, vary by state
| or province, and often depend on the employee's circumstances (residency,
| age, tax code, opt-outs, salary bands).
|
| They are here so the engine has something sensible to calculate with and so
| the shape of each country's deductions is correct. Every rate must be checked
| against current legislation, and payroll signed off by someone qualified,
| before it is used to pay anybody.
|
| Rates are seeded into salary_components, which are editable per workspace, so
| correcting them does not require a code change.
|
| Progressive income taxes (TDS, PAYE, Federal Tax, PAYG) are NOT modelled as
| flat percentages here. They are marked 'manual' so an amount is entered or
| supplied by an integration rather than silently miscalculated from a single
| rate.
|
*/

return [

    /** Fallback when a workspace has not chosen one. */
    'default_country' => env('PAYROLL_DEFAULT_COUNTRY', 'IN'),

    'countries' => [

        'IN' => [
            'name' => 'India',
            'currency_code' => 'INR',
            'currency_symbol' => '₹',
            'date_format' => 'd/m/Y',
            'has_income_tax' => true,
            'tax_id_label' => 'PAN',
            'bank_code_label' => 'IFSC code',
            'statutory_components' => [
                ['code' => 'PF', 'name' => 'Provident Fund', 'type' => 'deduction', 'calculation' => 'percent_of_basic', 'value' => 12.0],
                ['code' => 'ESIC', 'name' => 'ESIC', 'type' => 'deduction', 'calculation' => 'percent_of_gross', 'value' => 0.75],
                ['code' => 'PT', 'name' => 'Professional Tax', 'type' => 'deduction', 'calculation' => 'fixed', 'value' => 200.0],
                ['code' => 'TDS', 'name' => 'Income Tax (TDS)', 'type' => 'deduction', 'calculation' => 'manual', 'value' => 0.0],
            ],
        ],

        'US' => [
            'name' => 'United States',
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'date_format' => 'm/d/Y',
            'has_income_tax' => true,
            'tax_id_label' => 'SSN',
            'bank_code_label' => 'Routing number',
            'statutory_components' => [
                ['code' => 'FED_TAX', 'name' => 'Federal Income Tax', 'type' => 'deduction', 'calculation' => 'manual', 'value' => 0.0],
                ['code' => 'STATE_TAX', 'name' => 'State Income Tax', 'type' => 'deduction', 'calculation' => 'manual', 'value' => 0.0],
                ['code' => 'SS', 'name' => 'Social Security', 'type' => 'deduction', 'calculation' => 'percent_of_gross', 'value' => 6.2],
                ['code' => 'MEDICARE', 'name' => 'Medicare', 'type' => 'deduction', 'calculation' => 'percent_of_gross', 'value' => 1.45],
            ],
        ],

        'GB' => [
            'name' => 'United Kingdom',
            'currency_code' => 'GBP',
            'currency_symbol' => '£',
            'date_format' => 'd/m/Y',
            'has_income_tax' => true,
            'tax_id_label' => 'National Insurance number',
            'bank_code_label' => 'Sort code',
            'statutory_components' => [
                // Depends on the employee's tax code, so never a flat rate.
                ['code' => 'PAYE', 'name' => 'PAYE Income Tax', 'type' => 'deduction', 'calculation' => 'manual', 'value' => 0.0],
                ['code' => 'NI', 'name' => 'National Insurance', 'type' => 'deduction', 'calculation' => 'percent_of_gross', 'value' => 8.0],
            ],
        ],

        'AE' => [
            'name' => 'United Arab Emirates',
            'currency_code' => 'AED',
            'currency_symbol' => 'AED ',
            'date_format' => 'd/m/Y',
            'has_income_tax' => false,
            'tax_id_label' => 'Emirates ID',
            'bank_code_label' => 'IBAN',
            'statutory_components' => [
                // No personal income tax. GPSSA applies to UAE and GCC
                // nationals only, so it is off by default.
                ['code' => 'GPSSA', 'name' => 'GPSSA (nationals only)', 'type' => 'deduction', 'calculation' => 'percent_of_basic', 'value' => 5.0],
            ],
        ],

        'CA' => [
            'name' => 'Canada',
            'currency_code' => 'CAD',
            'currency_symbol' => 'C$',
            'date_format' => 'Y-m-d',
            'has_income_tax' => true,
            'tax_id_label' => 'SIN',
            'bank_code_label' => 'Transit number',
            'statutory_components' => [
                ['code' => 'CPP', 'name' => 'Canada Pension Plan', 'type' => 'deduction', 'calculation' => 'percent_of_gross', 'value' => 5.95],
                ['code' => 'EI', 'name' => 'Employment Insurance', 'type' => 'deduction', 'calculation' => 'percent_of_gross', 'value' => 1.64],
                ['code' => 'FED_TAX', 'name' => 'Federal Income Tax', 'type' => 'deduction', 'calculation' => 'manual', 'value' => 0.0],
            ],
        ],

        'AU' => [
            'name' => 'Australia',
            'currency_code' => 'AUD',
            'currency_symbol' => 'A$',
            'date_format' => 'd/m/Y',
            'has_income_tax' => true,
            'tax_id_label' => 'Tax File Number',
            'bank_code_label' => 'BSB',
            'statutory_components' => [
                ['code' => 'PAYG', 'name' => 'PAYG Withholding', 'type' => 'deduction', 'calculation' => 'manual', 'value' => 0.0],
                // Employer contribution: shown on the slip but not deducted
                // from the employee's pay.
                ['code' => 'SUPER', 'name' => 'Superannuation (employer)', 'type' => 'employer_contribution', 'calculation' => 'percent_of_basic', 'value' => 11.5],
            ],
        ],

        'SG' => [
            'name' => 'Singapore',
            'currency_code' => 'SGD',
            'currency_symbol' => 'S$',
            'date_format' => 'd/m/Y',
            'has_income_tax' => true,
            'tax_id_label' => 'NRIC/FIN',
            'bank_code_label' => 'Bank code',
            'statutory_components' => [
                // Employee share; the rate falls with age and applies to
                // citizens and permanent residents only.
                ['code' => 'CPF', 'name' => 'CPF (employee)', 'type' => 'deduction', 'calculation' => 'percent_of_gross', 'value' => 20.0],
            ],
        ],

    ],

    /*
    | Earnings offered by default when building a structure. Workspaces may add
    | their own; these simply save typing.
    */
    'default_earnings' => [
        ['code' => 'HRA', 'name' => 'House Rent Allowance', 'calculation' => 'percent_of_basic', 'value' => 40.0],
        ['code' => 'MEDICAL', 'name' => 'Medical Allowance', 'calculation' => 'fixed', 'value' => 0.0],
        ['code' => 'TRANSPORT', 'name' => 'Transport Allowance', 'calculation' => 'fixed', 'value' => 0.0],
        ['code' => 'BONUS', 'name' => 'Bonus', 'calculation' => 'fixed', 'value' => 0.0],
        ['code' => 'INCENTIVE', 'name' => 'Incentives', 'calculation' => 'fixed', 'value' => 0.0],
        ['code' => 'OVERTIME', 'name' => 'Overtime', 'calculation' => 'fixed', 'value' => 0.0],
        ['code' => 'COMMISSION', 'name' => 'Commission', 'calculation' => 'fixed', 'value' => 0.0],
        ['code' => 'OTHER_EARNING', 'name' => 'Other Allowances', 'calculation' => 'fixed', 'value' => 0.0],
    ],

    'default_deductions' => [
        ['code' => 'LOAN', 'name' => 'Loan Repayment', 'calculation' => 'fixed', 'value' => 0.0],
        ['code' => 'ADVANCE', 'name' => 'Salary Advance', 'calculation' => 'fixed', 'value' => 0.0],
        ['code' => 'OTHER_DEDUCTION', 'name' => 'Other Deductions', 'calculation' => 'fixed', 'value' => 0.0],
    ],

];
