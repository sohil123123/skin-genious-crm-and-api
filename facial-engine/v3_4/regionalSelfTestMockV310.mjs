import fs from 'node:fs'
import {CORE_FEATURE_IDS} from './skinStateV2.schema.js'
if(process.env.FACIAL_V310_SELF_TEST!=='1')throw Error('Self-test mock cannot be used in production')
globalThis.fetch=async(url,options)=>{
 if(!String(url).includes('api.openai.com/v1/responses'))throw Error('Unexpected external request')
 const req=JSON.parse(options.body);fs.appendFileSync(process.env.MOCK_CALL_LOG,req.text.format.name+'\n')
 const value=req.text.format.name==='pairwise_compact_v393'?{comparison_meta:{pair_quality:'good',pair_quality_issues:[]},feature_comparisons:Object.fromEntries(CORE_FEATURE_IDS.map(id=>[id,{global_change_direction:'stable',global_change_confidence:'high',zones_visibly_improved:[],zones_visibly_worsened:[],transient_reactivity_note:'',global_summary:'Synthetic stable evidence.'}]))}:JSON.parse(fs.readFileSync(process.env.MOCK_REGIONAL_FIXTURE))
 return {ok:true,status:200,json:async()=>({status:'completed',id:'mock',output:[{type:'message',content:[{type:'output_text',text:JSON.stringify(value)}]}]})}
}
