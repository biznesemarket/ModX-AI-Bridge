# Policy Engine

Status: **Review**

Policy evaluation is separate from token scope evaluation.

`scope` answers: *may this principal request the operation?*

`policy` answers: *is this operation permitted in the current Bridge/site state?*

This separation prevents a broadly scoped token from overriding deployment safety controls.
