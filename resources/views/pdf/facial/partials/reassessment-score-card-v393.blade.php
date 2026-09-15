<table width="100%" class="card-soft pad" cellpadding="0" cellspacing="0"><tr><td style="padding:3mm;">
<div class="mini-value">{{ data_get($item,'parameter_name') }}</div>
<div class="section-copy" style="margin-top:1mm;">Before: {{ data_get($item,'before_treatment_score_or_label') }} | After: {{ data_get($item,'post_treatment_score_or_label') }}</div>
<div class="mini-label" style="margin-top:1mm;">{{ data_get($item,'result')==='follow_up'?'Follow-up':ucfirst(data_get($item,'result','stable')) }}</div>
<div class="section-copy" style="margin-top:1mm;">{{ data_get($item,'score_explanation') }}</div>
</td></tr></table>
