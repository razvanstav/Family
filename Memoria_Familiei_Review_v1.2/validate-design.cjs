const fs=require('fs');
const path=require('path');
const assert=require('assert/strict');
const read=n=>fs.readFileSync(path.join(__dirname,n),'utf8');
const spec=JSON.parse(read('openapi.v1.json'));
const catalog=JSON.parse(read('schema.catalog.json'));
const sql=read('schema.postgresql.sql');
const checks=[];
function check(name,fn){fn();checks.push('PASS '+name);}
function resolve(r){assert(r.startsWith('#/'));return r.slice(2).split('/').reduce((v,k)=>{assert(v&&k in v,'Missing reference '+r);return v[k];},spec);}
function visit(v,fn,p='$'){if(!v||typeof v!=='object')return;fn(v,p);for(const[k,x]of Object.entries(v))visit(x,fn,p+'.'+k);}
function validate(s,v,p='$'){
 if(s.$ref)return validate(resolve(s.$ref),v,p);
 if(s.allOf)for(const x of s.allOf){if(x.if){let matches=true;try{validate(x.if,v,p);}catch{matches=false;}if(matches&&x.then)validate(x.then,v,p);if(!matches&&x.else)validate(x.else,v,p);}else validate(x,v,p);}
 if(s.anyOf)assert(s.anyOf.some(x=>{try{validate(x,v,p);return true;}catch{return false;}}),p+' anyOf');
 if(s.not){let matches=true;try{validate(s.not,v,p);}catch{matches=false;}assert(!matches,p+' not');}
 if(s.const!==undefined)assert.deepEqual(v,s.const,p+' const');
 if(s.enum)assert(s.enum.includes(v),p+' enum');
 if(s.type){const ts=[s.type].flat();const ok=ts.some(t=>t==='null'?v===null:t==='integer'?Number.isInteger(v):t==='array'?Array.isArray(v):t==='object'?v!==null&&typeof v==='object'&&!Array.isArray(v):typeof v===t);assert(ok,p+' type '+s.type);}
 if(v===null)return;
 if(typeof v==='object'&&!Array.isArray(v)){
   for(const k of s.required||[])assert(Object.hasOwn(v,k),p+' required '+k);
   if(s.minProperties)assert(Object.keys(v).length>=s.minProperties,p+' minProperties');
   for(const[k,x]of Object.entries(v)){if(s.properties?.[k])validate(s.properties[k],x,p+'.'+k);else if(s.additionalProperties===false)assert.fail(p+' unknown '+k);else if(s.additionalProperties&&typeof s.additionalProperties==='object')validate(s.additionalProperties,x,p+'.'+k);}
 }
 if(Array.isArray(v)){if(s.minItems!==undefined)assert(v.length>=s.minItems,p+' minItems');if(s.maxItems!==undefined)assert(v.length<=s.maxItems,p+' maxItems');v.forEach((x,i)=>s.items&&validate(s.items,x,p+'['+i+']'));}
 if(typeof v==='string'){if(s.minLength!==undefined)assert(v.length>=s.minLength,p+' minLength');if(s.maxLength!==undefined)assert(v.length<=s.maxLength,p+' maxLength');if(s.pattern)assert(new RegExp(s.pattern).test(v),p+' pattern');if(s.format==='uuid')assert(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(v),p+' uuid');}
 if(typeof v==='number'){if(s.minimum!==undefined)assert(v>=s.minimum,p+' minimum');if(s.maximum!==undefined)assert(v<=s.maximum,p+' maximum');if(s.exclusiveMinimum!==undefined)assert(v>s.exclusiveMinimum,p+' exclusiveMinimum');}
}
check('OpenAPI local references resolve',()=>visit(spec,v=>{if(v.$ref)resolve(v.$ref);}));
let operations=0;
check('Operation IDs unique and path parameters exact',()=>{
 const ids=new Set();for(const[p,ops]of Object.entries(spec.paths))for(const[m,o]of Object.entries(ops)){
   operations++;assert(!ids.has(o.operationId),o.operationId);ids.add(o.operationId);
   const vars=[...p.matchAll(/\{([^}]+)\}/g)].map(x=>x[1]).sort();const params=(o.parameters||[]).map(v=>v.$ref?resolve(v.$ref):v);
   assert.deepEqual(params.filter(v=>v.in==='path').map(v=>v.name).sort(),vars,p);
   assert(params.filter(v=>v.in==='path').every(v=>v.required===true));
   assert(Object.keys(o.responses).some(v=>v.startsWith('2')),p+' success response');
 }
});
check('Schema required keys exist and examples satisfy declared shapes',()=>{
 visit(spec,(v,p)=>{if(v.type==='object'&&v.properties)for(const k of v.required||[])assert(k in v.properties,p+' '+k);if(v.schema&&Object.hasOwn(v,'example'))validate(v.schema,v.example,p);});
});
check('Nullable patch does not allow null visibility/currency/status',()=>{
 for(const [n,s]of Object.entries(spec.components.schemas).filter(([n])=>n.endsWith('Patch')))for(const k of ['visibility','currency','status'])if(s.properties?.[k])assert(![s.properties[k].type].flat().includes('null'),n+'.'+k);
});
check('Representative valid and invalid payloads',()=>{
 validate(spec.components.schemas.VehicleCreate,{label:'Masina',make:'Toyota',model:'Corolla',visibility:'family'});
 assert.throws(()=>validate(spec.components.schemas.VehicleCreate,{label:'Masina',make:'Toyota',model:'Corolla',family_id:'bad'}));
 assert.throws(()=>validate(spec.components.schemas.RatingCreate,{score:6}));
 assert.throws(()=>validate(spec.components.schemas.CookingSessionCreate,{cooked_at:'2026-10-20T12:00:00Z',participant_ids:[]}));
 assert.throws(()=>validate(spec.components.schemas.DocumentPatch,{media_ids:[]}));
 assert.throws(()=>validate(spec.components.schemas.CalendarItemCreate,{item_type:'event',title:'Test'}));
 validate(spec.components.schemas.ObligationPatch,{due_on:'2027-10-20'});
});
check('SQL FK tables and columns exist in schema catalog',()=>{
 const byName=Object.fromEntries(catalog.map(t=>[t.name,t]));
 for(const m of sql.matchAll(/ALTER TABLE ([a-z_]+) ADD FOREIGN KEY \(([^)]+)\) REFERENCES ([a-z_]+) \(([^)]+)\)/g)){
   for(const [t,cs]of [[m[1],m[2]],[m[3],m[4]]]){assert(byName[t],t);for(const c of cs.split(',').map(v=>v.trim()))assert(byName[t].columns.some(x=>x.name===c),t+'.'+c);}
 }
});
check('Tenant tables enable and force RLS with checks',()=>{
 for(const t of catalog.filter(t=>t.tenant)){assert(sql.includes('ALTER TABLE '+t.name+' ENABLE ROW LEVEL SECURITY;'));assert(sql.includes('ALTER TABLE '+t.name+' FORCE ROW LEVEL SECURITY;'));assert(sql.includes('CREATE POLICY tenant_scope ON '+t.name+' USING'));}
});
check('Evidence expiry mode guarded at both ends and subjects have deferred guards',()=>{
 assert(sql.includes("CHECK(expiry_mode='independent' OR expires_on IS NULL)"));
 for(const n of ['pet_records','vehicle_service_logs','vehicle_obligations'])assert(sql.includes('ALTER TABLE '+n+' ADD FOREIGN KEY (family_id, evidence_document_id, evidence_mode) REFERENCES documents (family_id, id, expiry_mode)'));
 for(const n of ['family_members','pets','vehicles'])assert(sql.includes('CREATE CONSTRAINT TRIGGER '+n+'_subject_owner'));
});
check('No duplicate table or named index and PostgreSQL identifiers <= 63 bytes',()=>{
 for(const re of [/CREATE TABLE ([a-z_]+)/g,/CREATE (?:UNIQUE )?INDEX ([a-z_]+)/g,/CREATE (?:CONSTRAINT )?TRIGGER ([a-z_]+)/g]){
   const names=[...sql.matchAll(re)].map(v=>v[1]);assert.equal(new Set(names).size,names.length);for(const n of names)assert(Buffer.byteLength(n)<=63,n);
 }
});
const report=read('review.html');
check('HTML local artifact links exist',()=>{
 for(const m of report.matchAll(/href="([^"]+)"/g)){const href=m[1];if(!href.startsWith('#')&&!href.startsWith('https:')&&href!=='validation.txt')assert(fs.existsSync(path.join(__dirname,href)),href);}
});
const phpSyntax=require('child_process').spawnSync('C:/Users/razva/php84/php.exe',['-l',path.join(__dirname,'contracts.reference.php')],{encoding:'utf8'});
check('PHP reference contracts pass php -l',()=>assert.equal(phpSyntax.status,0,phpSyntax.stderr));
const result=checks.join('\n')+`\n\n${operations} API operations; ${catalog.length} custom tables; ${catalog.filter(t=>t.tenant).length} tenant RLS tables.\n`+
 'Scope: static design checks and representative payload checks only. This is not a full OpenAPI standards validator.\n'+
 'PostgreSQL DDL execution, RLS behavior and deferred constraints: NOT RUN; PostgreSQL server is not installed in this workspace.\n'+
 'Application E2E and native adapters: NOT RUN; artifacts are a proposed design, not the application.\n';
fs.writeFileSync(path.join(__dirname,'validation.txt'),result,'utf8');console.log(result);
