export type PayrollRecord = { id:number; period:string; staff_id:number; staff_name:string; basic_salary:string; allowances:string; deductions:string; net_salary:string }
export type Expense = { id:number; staff_id:number; staff_name:string; title:string; category:string; amount:string; expense_date:string; reason:string|null; status:"pending"|"approved"|"rejected" }
