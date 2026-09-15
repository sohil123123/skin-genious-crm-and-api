import fs from 'node:fs/promises'
import path from 'node:path'
import {createHash,randomUUID} from 'node:crypto'
export const canonical=value=>Array.isArray(value)?value.map(canonical):value&&typeof value==='object'?Object.fromEntries(Object.keys(value).sort().map(k=>[k,canonical(value[k])])):value
export const measurementKey=identity=>createHash('sha256').update(JSON.stringify(canonical(identity))).digest('hex')
const delay=ms=>new Promise(r=>setTimeout(r,ms))
export async function cachedMeasurement({directory,identity,produce,waitMs=900000}){
 if(!directory)throw Error('FACIAL_V310_CACHE_DIR is required for immutable measurement reuse')
 await fs.mkdir(directory,{recursive:true,mode:0o700});const key=measurementKey(identity),file=path.join(directory,key+'.json'),lock=file+'.lock';const start=Date.now()
 const read=async()=>{try{const v=JSON.parse(await fs.readFile(file,'utf8'));if(v.key!==key||!v.value)throw Error('Invalid immutable measurement cache');return v.value}catch(e){if(e.code==='ENOENT')return null;throw e}}
 while(true){const hit=await read();if(hit)return {value:hit,cache_hit:true,key}
  let handle
  try{handle=await fs.open(lock,'wx',0o600)}catch(e){if(e.code!=='EEXIST')throw e;if(Date.now()-start>=waitMs)throw Error('Measurement already running or stale lock; inspect server job before retrying.');await delay(100);continue}
  try{const raced=await read();if(raced)return{value:raced,cache_hit:true,key};const value=await produce();const temp=file+'.'+randomUUID()+'.tmp';try{await fs.writeFile(temp,JSON.stringify({key,value}),{mode:0o600});await fs.rename(temp,file)}finally{await fs.unlink(temp).catch(()=>{})}return {value,cache_hit:false,key}}
  finally{await handle.close();await fs.unlink(lock).catch(()=>{})}
 }
}
