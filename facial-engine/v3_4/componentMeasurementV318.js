import {VERSION,FORMULA_VERSION,PARAMETERS,IDS,MODES,ANCHORS,INSTRUCTIONS} from './componentContractV318.js';
export {VERSION as MEASUREMENT_VERSION,FORMULA_VERSION};
export const DEVICE_PROFILE={id:'aia_five_modes_v318',white:'visible appearance',surface_polarized:'surface support',subsurface_polarized:'pigment and redness support',red:'context only',woods_uv:'context only'};
const obj=properties=>({type:'object',properties,required:Object.keys(properties),additionalProperties:false});
const number={type:['number','null'],minimum:0,maximum:1};
const states=['observed','obstructed','makeup','unreliable'];
const regionSchema=p=>obj({state:{type:'string',enum:states},confidence:{type:'number',minimum:0,maximum:1},modes:{type:'array',items:{type:'string',enum:p.modes}},evidence:{type:'string'},components:obj(Object.fromEntries(Object.keys(p.components).map(k=>[k,number])))});
export function wireSchema(){return obj({version:{type:'string',enum:[VERSION]},parameters:obj(Object.fromEntries(IDS.map(id=>[id,obj({summary:{type:'string'},regions:obj(Object.fromEntries(Object.keys(PARAMETERS[id].regions).map(r=>[r,regionSchema(PARAMETERS[id])])) )})]))),skin_type:obj({label:{type:'string',enum:['dry','oily','combination','balanced','not_assessed']},confidence:{type:'number',minimum:0,maximum:1},observation:{type:'string'}})});}
export const measurementFormat=()=>({type:'json_schema',name:'regional_components_v318',strict:true,schema:wireSchema()});
export function measurementPrompt(){return `V3.18 BASELINE REGIONAL COMPONENT OBSERVATION. ${INSTRUCTIONS}\nANCHORS ${JSON.stringify(ANCHORS)}\nDEFINITIONS ${JSON.stringify(PARAMETERS)}\nReturn the schema only. For each region evaluate all its components; absent findings are observed severity 0 with positive confidence and evidence. If the skin or an essential component is uninterpretable, mark the region obstructed/makeup/unreliable and set ALL components null, confidence 0. Never fill missing components with 0. Keep patient identity, treatments, target scores and prior reports out of the observations.`;}
export function exact(v,keys,label){if(!v||typeof v!=='object'||Array.isArray(v)||Object.keys(v).sort().join('|')!==[...keys].sort().join('|'))throw Error(`Invalid keys: ${label}`);}
export function decodeMeasurements(input){
 const v=structuredClone(input);exact(v,['version','parameters','skin_type'],'measurement');if(v.version!==VERSION)throw Error(`Expected ${VERSION}, received ${v.version}`);exact(v.parameters,IDS,'parameters');
 for(const id of IDS){const p=PARAMETERS[id],q=v.parameters[id];exact(q,['summary','regions'],id);if(typeof q.summary!=='string'||!q.summary.trim())throw Error(`Missing summary ${id}`);exact(q.regions,Object.keys(p.regions),id+'.regions');
 for(const[r,row]of Object.entries(q.regions)){exact(row,['state','confidence','modes','evidence','components'],id+'.'+r);exact(row.components,Object.keys(p.components),id+'.'+r+'.components');
 if(!states.includes(row.state)||typeof row.confidence!=='number'||!Number.isFinite(row.confidence)||row.confidence<0||row.confidence>1||typeof row.evidence!=='string'||!row.evidence.trim()||!Array.isArray(row.modes)||new Set(row.modes).size!==row.modes.length||row.modes.some(m=>!p.modes.includes(m)))throw Error(`Invalid observation ${id}.${r}`);
 const values=Object.values(row.components);
 if(row.state==='observed'){if(row.confidence===0||!row.modes.includes('white')||values.some(x=>typeof x!=='number'||!Number.isFinite(x)||x<0||x>1))throw Error(`Observed region needs white-light support and numerical components ${id}.${r}`);}
 else if(row.confidence!==0||values.some(x=>x!==null))throw Error(`Unavailable region must have null components and zero confidence ${id}.${r}`);
 }}
 exact(v.skin_type,['label','confidence','observation'],'skin_type');if(!['dry','oily','combination','balanced','not_assessed'].includes(v.skin_type.label)||typeof v.skin_type.confidence!=='number'||!Number.isFinite(v.skin_type.confidence)||v.skin_type.confidence<0||v.skin_type.confidence>1||typeof v.skin_type.observation!=='string')throw Error('Invalid skin_type');return v;
}
