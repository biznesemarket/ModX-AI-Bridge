(() => {
    'use strict';

    const app = document.getElementById('aibridge-app');
    const workspace = document.getElementById('aibridge-resource-workspace');
    if (!app || !workspace) return;

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const connector = (typeof MODx !== 'undefined' && MODx.config) ? MODx.config.connector_url : null;
    const state = { data: null, active: 'system', resource: null, contract: null, qa: null, tree: [], filters: {query:'', template_id:'', tv_name:'', tv_value:''} };

    function request(params) {
        return new Promise((resolve, reject) => {
            if (typeof MODx === 'undefined' || !MODx.Ajax || !connector) return reject(new Error('MODX Manager connector is unavailable.'));
            MODx.Ajax.request({url: connector, params, listeners: {success: resolve, failure: reject}});
        });
    }
    function call(action, extra = {}) { return request(Object.assign({action}, extra)); }
    function table(columns, rows) {
        return `<div class="aibridge-table-wrap"><table class="aibridge-table"><thead><tr>${columns.map(c=>`<th>${esc(c.label)}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${columns.map(c=>`<td>${c.render ? c.render(r) : esc(r[c.key])}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="${columns.length}">Нет данных</td></tr>`}</tbody></table></div>`;
    }
    function tabs() {
        return ['system','profiles','tokens','policies','jobs','audit','fingerprints','resources'].map(t=>`<button class="${state.active===t?'is-active':''}" data-tab="${t}">${({system:'System',profiles:'Profiles',tokens:'Tokens',policies:'Policies',jobs:'Jobs',audit:'Audit',fingerprints:'Fingerprints',resources:'Resources'})[t]}</button>`).join('');
    }
    function renderConsole() {
        const d = state.data; if (!d) return;
        const o = d.overview;
        let body = '';
        if (state.active === 'system') body = `<div class="aibridge-grid"><section class="aibridge-card"><h2>System status</h2><dl class="aibridge-kv"><dt>Bridge</dt><dd>${esc(o.component)} ${esc(o.version)}</dd><dt>MODX</dt><dd>${esc(o.modx)}</dd><dt>PHP</dt><dd>${esc(o.php)}</dd><dt>Environment</dt><dd>${esc(o.environment)}</dd></dl></section><section class="aibridge-card"><h2>Readiness</h2><div class="aibridge-readiness ${o.readiness.status === 'ready' ? 'is-ready':'is-not-ready'}">${esc(o.readiness.status)}</div><ul>${Object.entries(o.readiness.checks).map(([k,v])=>`<li>${esc(k)}: ${v ? 'OK':'FAIL'}</li>`).join('')}</ul></section></div>`;
        else if (state.active === 'profiles') body = table([{label:'ID',key:'id'},{label:'Name',key:'name'},{label:'Site key',key:'site_key'},{label:'Environment',key:'environment'},{label:'Status',key:'status',render:r=>`${esc(r.status)} ${r.status!=='retired'?`<button data-action="profile" data-id="${r.id}" data-status="${r.status==='active'?'disabled':'active'}">${r.status==='active'?'Disable':'Enable'}</button>`:''}`},{label:'Base URL',key:'base_url'}], d.profiles);
        else if (state.active === 'tokens') body = table([{label:'ID',key:'id'},{label:'Profile',key:'profile_id'},{label:'Name',key:'name'},{label:'Status',key:'status',render:r=>`${esc(r.status)} ${r.status!=='revoked'?`<button data-action="token" data-id="${r.id}" data-status="revoked">Revoke</button>`:''}`},{label:'Scopes',render:r=>esc(r.scopes.join(', '))},{label:'Expires',key:'expires_at'}], d.tokens);
        else if (state.active === 'policies') body = table([{label:'ID',key:'id'},{label:'Profile',key:'profile_id'},{label:'Name',key:'name'},{label:'Status',key:'status',render:r=>`${esc(r.status)} <button data-action="policy" data-id="${r.id}" data-status="${r.status==='active'?'disabled':'active'}">${r.status==='active'?'Disable':'Enable'}</button>`},{label:'Rules',render:r=>`<code>${esc(JSON.stringify(r.rules))}</code>`}], d.policies);
        else if (state.active === 'jobs') body = table([{label:'ID',key:'id'},{label:'Profile',key:'profile_id'},{label:'Type',key:'type'},{label:'Status',key:'status'},{label:'Progress',render:r=>`${esc(r.progress)}%`},{label:'Attempts',render:r=>`${r.attempts}/${r.max_attempts}`},{label:'Worker',key:'locked_by'}], d.jobs);
        else if (state.active === 'audit') body = table([{label:'Time',key:'created_at'},{label:'Event',key:'event'},{label:'Actor',render:r=>`${esc(r.actor_type)}:${esc(r.actor_id)}`},{label:'Operation',key:'operation'},{label:'Resource',key:'resource_id'},{label:'Request',key:'request_id'}], d.audit);
        else if (state.active === 'fingerprints') body = `<div class="aibridge-card"><p>Выберите две версии fingerprint для сравнения.</p>${table([{label:'ID',key:'id'},{label:'Profile',key:'profile_id'},{label:'Algorithm',key:'algorithm'},{label:'Fingerprint',key:'fingerprint'},{label:'Created',key:'created_at'}], d.fingerprints)}<div class="aibridge-inline-form"><input id="fp-from" placeholder="From ID"><input id="fp-to" placeholder="To ID"><button id="fp-diff">Compare</button></div><pre id="fp-diff-result"></pre></div>`;
        else body = resourceExplorer();
        app.innerHTML = `<div class="aibridge-manager"><header class="aibridge-manager__header"><div><h1>MODX AI Bridge</h1><p>Operations Console</p></div><button id="aibridge-refresh">Refresh</button></header><nav class="aibridge-tabs">${tabs()}</nav>${body}</div>`;
        app.querySelectorAll('[data-tab]').forEach(el=>el.addEventListener('click',()=>{state.active=el.dataset.tab;renderConsole(); if(state.active==='resources') loadTree();}));
        app.querySelector('#aibridge-refresh')?.addEventListener('click', load);
        app.querySelectorAll('[data-action]').forEach(el=>el.addEventListener('click',()=>mutate(el.dataset.action, el.dataset.id, el.dataset.status)));
        app.querySelector('#fp-diff')?.addEventListener('click', diffFingerprints);
        bindResourceUI();
    }
    function resourceExplorer() {
        return `<div class="aibridge-resource-layout"><aside class="aibridge-card aibridge-resource-tree"><h2>Resources</h2><button id="resource-new">+ New resource</button><div class="aibridge-resource-toolbar"><input id="resource-search" value="${esc(state.filters.query)}" placeholder="Поиск…"><input id="resource-template" value="${esc(state.filters.template_id)}" placeholder="Template ID"><input id="resource-tv-name" value="${esc(state.filters.tv_name)}" placeholder="TV name"><input id="resource-tv-value" value="${esc(state.filters.tv_value)}" placeholder="TV value"><button id="resource-search-btn">Search</button></div><div id="resource-tree-list">Loading…</div></aside><main class="aibridge-card aibridge-resource-detail"><div id="resource-detail">Выберите ресурс.</div></main></div>`;
    }
    function bindResourceUI() {
        app.querySelector('#resource-search-btn')?.addEventListener('click', searchResources);
        app.querySelector('#resource-new')?.addEventListener('click', newResource);
        app.querySelector('#resource-search')?.addEventListener('keydown', e=>{if(e.key==='Enter')searchResources();});
        app.querySelectorAll('[data-resource-id]').forEach(el=>el.addEventListener('click',()=>selectResource(Number(el.dataset.resourceId))));
        app.querySelector('#fp-diff')?.addEventListener('click', diffFingerprints);
    }
    async function loadTree() {
        if (state.active !== 'resources') return;
        const box=app.querySelector('#resource-tree-list'); if(!box)return;
        try { const r=await call('aibridge/manager/resources',{mode:'tree',parent:0,limit:200}); if(!r.success)throw new Error(r.message||'Failed'); state.tree=r.items||[]; box.innerHTML=state.tree.length?state.tree.map(renderResourceNode).join(''):'Нет ресурсов'; bindResourceUI(); } catch(e){box.innerHTML=`<div class="aibridge-error">${esc(e.message)}</div>`;}
    }
    function renderResourceNode(r){return `<div class="aibridge-resource-node"><button data-resource-id="${r.id}" class="aibridge-resource-link">${esc(r.pagetitle||'(без названия)')} <small>#${r.id}</small></button></div>${r.children?.length?`<div class="aibridge-resource-children">${r.children.map(renderResourceNode).join('')}</div>`:''}`;}
    async function searchResources(){
        state.filters.query=app.querySelector('#resource-search')?.value||''; state.filters.template_id=app.querySelector('#resource-template')?.value||''; state.filters.tv_name=app.querySelector('#resource-tv-name')?.value||''; state.filters.tv_value=app.querySelector('#resource-tv-value')?.value||'';
        const box=app.querySelector('#resource-tree-list'); if(!box)return; box.textContent='Loading…';
        try { const r=await call('aibridge/manager/resources',{mode:'search',query:state.filters.query,template_id:state.filters.template_id,tv_name:state.filters.tv_name,tv_value:state.filters.tv_value,limit:200}); if(!r.success)throw new Error(r.message||'Failed'); box.innerHTML=(r.items||[]).map(renderResourceNode).join('')||'Нет результатов'; bindResourceUI(); }catch(e){box.innerHTML=`<div class="aibridge-error">${esc(e.message)}</div>`;}
    }
    async function selectResource(id){
        try { const r=await call('aibridge/manager/resources',{mode:'get',id}); if(!r.success)throw new Error(r.message||'Failed'); state.resource=r.item; state.contract=null; state.qa=null; renderResourceDetail(); await Promise.all([loadContract(id), loadQA(id)]); renderResourceDetail(); }catch(e){app.querySelector('#resource-detail').innerHTML=`<div class="aibridge-error">${esc(e.message)}</div>`;}
    }
    async function loadContract(id){ const r=await call('aibridge/manager/resources',{mode:'contract',id}); if(r.success)state.contract=r.item; }
    async function loadQA(id){ const r=await call('aibridge/manager/resources',{mode:'qa',id,candidate:JSON.stringify({})}); if(r.success)state.qa=r.item; }
    function renderResourceDetail(){
        const box=app.querySelector('#resource-detail'); if(!box||!state.resource)return; const r=state.resource; const qa=state.qa; const c=state.contract;
        box.innerHTML=`<div class="aibridge-detail-header"><div><h2>${esc(r.pagetitle)} <small>#${r.id}</small></h2><p>Template: ${esc(r.template)} · Parent: ${esc(r.parent)} · Alias: ${esc(r.alias)}</p></div><div><button data-op="resource.preview" data-sync="1">Preview</button><button data-op="resource.update">Save as Job</button><button data-op="resource.publish">Publish Job</button><button data-op="resource.delete" class="is-danger">Delete Job</button></div></div>
        <div class="aibridge-grid"><section class="aibridge-card"><h3>Content</h3><label>Page title<input id="edit-pagetitle" value="${esc(r.pagetitle)}"></label><label>Description<textarea id="edit-description">${esc(r.description)}</textarea></label><label>Introtext<textarea id="edit-introtext">${esc(r.introtext)}</textarea></label><label>Content<textarea id="edit-content" class="aibridge-code">${esc(r.content)}</textarea></label><label>Alias<input id="edit-alias" value="${esc(r.alias)}"></label></section><section class="aibridge-card"><h3>Contract</h3><pre>${esc(c?JSON.stringify(c,null,2):'Loading…')}</pre><h3>QA</h3><div class="aibridge-readiness ${qa?.valid?'is-ready':'is-not-ready'}">${qa? (qa.valid?'valid':'invalid'):'Loading…'}</div><pre>${esc(qa?JSON.stringify(qa,null,2):'')}</pre></section></div><div id="resource-operation-result"></div>`;
        box.querySelectorAll('[data-op]').forEach(b=>b.addEventListener('click',()=>executeResource(b.dataset.op,b.dataset.sync==='1')));
    }
    function newResource(){
        state.resource={id:0,parent:0,pagetitle:'',description:'',introtext:'',content:'<h1></h1>\n<p></p>',alias:'',template:0}; state.contract=null; state.qa=null;
        const box=app.querySelector('#resource-detail');
        if(!box)return;
        box.innerHTML=`<div class="aibridge-detail-header"><div><h2>New resource</h2><p>Создание выполняется только через Queue + ResourceExecutionService.</p></div><button data-op="resource.create">Create Job</button></div><div class="aibridge-card"><label>Template ID<input id="edit-template" value="" placeholder="required"></label><label>Parent ID<input id="edit-parent" value="0"></label><label>Page title<input id="edit-pagetitle" value=""></label><label>Description<textarea id="edit-description"></textarea></label><label>Introtext<textarea id="edit-introtext"></textarea></label><label>Content<textarea id="edit-content" class="aibridge-code"><h1></h1>\n<p></p></textarea></label><label>Alias<input id="edit-alias" value=""></label></div><div id="resource-operation-result"></div>`;
        box.querySelector('[data-op]')?.addEventListener('click',()=>executeResource('resource.create',false));
    }
    function currentInput(){ const r=state.resource||{}; return {id:r.id||undefined,template:Number(app.querySelector('#edit-template')?.value||r.template||0),parent:Number(app.querySelector('#edit-parent')?.value||r.parent||0),pagetitle:app.querySelector('#edit-pagetitle')?.value||'',description:app.querySelector('#edit-description')?.value||'',introtext:app.querySelector('#edit-introtext')?.value||'',content:app.querySelector('#edit-content')?.value||'',alias:app.querySelector('#edit-alias')?.value||'',}; }
    async function executeResource(operation,sync=false){
        const result=app.querySelector('#resource-operation-result'); if(result)result.innerHTML='<div class="aibridge-card">Выполнение…</div>';
        try { const r=await call('aibridge/manager/resource-operation',{operation,input:JSON.stringify(currentInput()),sync:sync?'1':'0'}); if(!r.success)throw new Error(r.message||r.error?.message||'Operation rejected'); if(sync){if(result)result.innerHTML=`<div class="aibridge-card"><h3>Preview</h3><pre>${esc(JSON.stringify(r,null,2))}</pre></div>`;return;} const jobId=r.job_id; if(result)result.innerHTML=`<div class="aibridge-card">Job #${esc(jobId)} создан. <span id="job-progress">queued</span></div>`; pollJob(jobId); }catch(e){if(result)result.innerHTML=`<div class="aibridge-card aibridge-error">${esc(e.message)}</div>`;}
    }
    async function pollJob(id){ let tries=0; const tick=async()=>{tries++;try{const r=await call('aibridge/manager/overview'); const job=(r.object||r.data)?.jobs?.find(j=>String(j.id)===String(id)); const el=document.getElementById('job-progress'); if(job&&el){el.textContent=`${job.status} ${job.progress}%`;if(job.status==='completed'||job.status==='failed'||job.status==='cancelled')return;} if(tries<30)setTimeout(tick,1000);}catch(e){}}; tick(); }
    async function diffFingerprints(){ const from=Number(app.querySelector('#fp-from')?.value||0),to=Number(app.querySelector('#fp-to')?.value||0); if(!from||!to)return;const out=app.querySelector('#fp-diff-result');try{const r=await call('aibridge/manager/resources',{mode:'fingerprint_diff',from_id:from,to_id:to});out.textContent=JSON.stringify(r,null,2);}catch(e){out.textContent=e.message;}}
    async function load(){
        app.innerHTML='<div class="aibridge-manager"><div class="aibridge-card">Loading…</div></div>';
        try{const r=await call('aibridge/manager/overview');if(!r.success)throw new Error(r.message||'Request failed.');state.data=r.object||r.data;renderConsole();if(state.active==='resources')loadTree();}catch(e){app.innerHTML=`<div class="aibridge-manager"><div class="aibridge-card aibridge-error">${esc(e.message||e)}</div></div>`;}
    }
    async function mutate(entity,id,status){try{const r=await call('aibridge/manager/action',{entity,id,status});if(!r.success)throw new Error(r.message||'Operation failed.');await load();}catch(e){window.alert(e.message||e);}}
    load();
})();
