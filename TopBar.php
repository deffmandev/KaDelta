<div class="FrameTitre">
    <img src="Images/LogoKaDelta.png" alt="Ka Delta Logo" style="height:172px;" />
    <h1>Ka Delta Modbus</h1>
    <div class="TitreGroupe">
        <button id="btn-page" class="groupe-btn">Page</button>
        <button id="btn-groupe" class="groupe-btn">Groupe</button>
        <button onclick="ClimGroupe()" class="groupe-btn">Action sur<br />groupe</button>
        <button id="btn-programmation" class="groupe-btn">Programmation</button>
        <button id="btn-defaut" class="groupe-btn">Défauts</button>
        <button onclick="MaintenanceWindows()" class="groupe-btn">Maintenance</button>
    </div>
    <div class="horloges">
        <span id="date"></span><br />
        <span id="heure" style="font-variant-numeric:tabular-nums;">
            <span id="h"></span><span id="colon" style="transition:.4s;opacity:0;">:</span><span id="m"></span>
        </span>
    </div>
    <script>
    (function(){
        // Horloge
        function updateDateHeure() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'numeric', day: 'numeric' };
            const dateStr = now.toLocaleDateString('fr-FR', options);
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const heures = now.getHours().toString().padStart(2, '0');
            const showColon = now.getSeconds() % 2 === 0;
            const dateEl = document.getElementById('date');
            if (!dateEl) return; // sécurité si plusieurs topbars
            dateEl.textContent = dateStr;
            const hEl=document.getElementById('h'); if (hEl) hEl.textContent = heures;
            const colon=document.getElementById('colon'); if (colon) colon.style.opacity = showColon ? '1' : '0';
            const mEl=document.getElementById('m'); if (mEl) mEl.textContent = minutes;
        }
        updateDateHeure();
        setInterval(updateDateHeure, 1000);

        // Initialisation unique TopBar
        if (!window.__topbarInit) {
            window.__topbarInit = true;

            // Injection des conteneurs communs si absents
            function ensureContainers(){
                const htmlBlocks = [
                    { id:'OverScreenWunites', html:'<div id="OverScreenWunites" class="OverScreen" style="display:none;position:fixed;left:0;top:0;width:100%;height:100%;z-index:1200;background:transparent;"><iframe id="OSWunite" title="Fenêtre unités" src="" frameborder="0" style="width:100%;height:100%;border:none;background:transparent;"></iframe></div>' },
                    { id:'overlay-groupe', html:'<div id="overlay-groupe" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:transparent;z-index:999;"></div>' },
                    { id:'popup-groupe', html:'<div id="popup-groupe" style="display:none;position:absolute;background:rgba(20,40,80,0.92);border-radius:18px;box-shadow:rgba(31,38,135,0.25) 0 8px 24px;padding:20px 20px 24px;z-index:1000;min-width:700px;max-width:90vw;backdrop-filter:blur(10px);border:1.5px solid rgba(255,255,255,0.18);"><h4 style="margin-top:0;font-size:1.15em;color:#fff;text-align:center;letter-spacing:1px;">Sélectionnez un groupe</h4><div id="groupe-list" style="display:flex;flex-direction:column;gap:18px;margin-top:16px;"></div></div>' },
                    { id:'overlay-page', html:'<div id="overlay-page" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:transparent;z-index:998;"></div>' },
                    { id:'popup-page', html:'<div id="popup-page" style="display:none;position:absolute;background:rgba(30,50,90,0.98);border-radius:18px;box-shadow:rgba(31,38,135,0.25) 0 8px 24px;padding:20px 22px 26px;z-index:1001;min-width:320px;max-width:90vw;backdrop-filter:blur(10px);border:1.5px solid rgba(255,255,255,0.18);"><h4 style="margin:0 0 14px;font-size:1.05em;color:#fff;text-align:center;letter-spacing:1px;">Ouvrir une page</h4><div id="page-list" style="display:flex;flex-direction:column;gap:16px;"></div></div>' }
                ];
                htmlBlocks.forEach(b=>{ if(!document.getElementById(b.id)) document.body.insertAdjacentHTML('beforeend', b.html); });
                if(!document.getElementById('topbar-transition-style')){
                    const st=document.createElement('style'); st.id='topbar-transition-style'; st.textContent=`
                        #page-transition-overlay{position:fixed;inset:0;z-index:3000;display:flex;align-items:center;justify-content:center;background:radial-gradient(circle at 40% 40%,rgba(15,23,42,.95),rgba(2,6,23,.98));color:#fff;font-family:sans-serif;font-size:1.1em;letter-spacing:1px;animation:fadeIn .15s ease forwards;}
                        #page-transition-overlay .spinner{width:70px;height:70px;border:6px solid #334155;border-top-color:#fff;border-radius:50%;animation:spin .8s linear infinite;filter:drop-shadow(0 0 6px #fff8);} 
                        #page-transition-overlay .msg{margin-top:18px;font-size:.8em;opacity:.85;text-transform:uppercase;letter-spacing:3px;}
                        @keyframes spin{to{transform:rotate(360deg)}}
                        @keyframes fadeIn{from{opacity:0}to{opacity:1}}
                        body.page-transitioning{cursor:progress !important;}
                    `; document.head.appendChild(st);
                }
            }
            ensureContainers();

            const isIndex = location.pathname.toLowerCase().indexOf('index.php') !== -1;

            // Mémoriser la dernière position de la souris pour réouverture automatique
            if(!window.__lastMouseTrack){
                window.__lastMouseTrack = true;
                window.__lastMouseX = null; window.__lastMouseY = null;
                document.addEventListener('mousemove', (e)=>{ window.__lastMouseX = e.clientX; window.__lastMouseY = e.clientY; }, { passive:true });
            }

            function saveGroupMousePosCookie(){
                if(window.__lastMouseX!=null && window.__lastMouseY!=null){
                    const v = window.__lastMouseX + ',' + window.__lastMouseY;
                    document.cookie = 'grpPos='+encodeURIComponent(v)+';path=/;max-age=6'; // court délai
                }
            }
            function readGroupMousePosCookie(){
                const m = document.cookie.match(/(?:^|; )grpPos=([^;]+)/);
                if(!m) return null;
                try { const [sx,sy] = decodeURIComponent(m[1]).split(','); const x=parseInt(sx,10), y=parseInt(sy,10); if(!isNaN(x)&&!isNaN(y)) return {x,y}; } catch(_){ }
                return null;
            }
            function clearGroupMousePosCookie(){ document.cookie = 'grpPos=;path=/;expires=Thu, 01 Jan 1970 00:00:00 GMT'; }

            // Fonctions globales (iframe overlay)
            window.ClimTest = function(IdSel){ const over=document.getElementById('OverScreenWunites'); const ifr=document.getElementById('OSWunite'); if(over&&ifr){ over.style.display='block'; ifr.src='Wunites.php?IdSel='+IdSel; } }
            window.ClimGroupe = function(){
                if(!isIndex){ location.href='index.php?action=actionGroupe'; return; }
                const over=document.getElementById('OverScreenWunites'); const ifr=document.getElementById('OSWunite'); if(over&&ifr){ over.style.display='block'; ifr.src='Wunites.php?Groupe='+(window.NumeroDeGroupeValide||'all'); }
            }
            window.MaintenanceWindows = function(){ const over=document.getElementById('OverScreenWunites'); const ifr=document.getElementById('OSWunite'); if(over&&ifr){ over.style.display='block'; ifr.src='Maintenance.php'; } }
            window.openProgrammation = function(){
                if(!isIndex){ location.href='index.php?action=programmation'; return; }
                const over=document.getElementById('OverScreenWunites'); const ifr=document.getElementById('OSWunite'); if(over&&ifr){ over.style.display='block'; ifr.src='Programme.php'; }
            }
            window.openDefaut = function(){ const over=document.getElementById('OverScreenWunites'); const ifr=document.getElementById('OSWunite'); if(over&&ifr){ over.style.display='block'; ifr.src='Defaut.php'; } }
            // Attache programm. & défaut boutons
            document.getElementById('btn-programmation')?.addEventListener('click', window.openProgrammation);
            // Redirection bouton Groupe si pas sur index
            const btnG=document.getElementById('btn-groupe');
            if(btnG){
                btnG.addEventListener('click', function(e){ if(!isIndex){ e.preventDefault(); e.stopImmediatePropagation(); saveGroupMousePosCookie(); location.href='index.php?action=showGroupMenu'; } }, true);
            }
            document.getElementById('btn-defaut')?.addEventListener('click', window.openDefaut);

            // Etat groupe sélectionné
            window.NumeroDeGroupeValide = 'all';

            // Filtrage (utilisé par index)
            window.filtrerParGroupeNumero = function(numero){
                document.querySelectorAll('.Vignette').forEach(v => {
                    const g = v.getAttribute('data-groupe');
                    if (numero === 'all' || (g === numero && g !== '0')) v.style.display = 'block'; else v.style.display = 'none';
                });
            }

            // Menu Groupes
            function initMenuGroupes(){
                const btnGroupe=document.getElementById('btn-groupe');
                const popup=document.getElementById('popup-groupe');
                const overlay=document.getElementById('overlay-groupe');
                const liste=document.getElementById('groupe-list');
                if(!btnGroupe||!popup||!overlay||!liste) return;
                function openGroupPopupAt(x,y){
                    overlay.style.display='block'; popup.style.display='block';
                    const r = popup.getBoundingClientRect(); const pad=16;
                    if (x + r.width > innerWidth - pad) x = innerWidth - r.width - pad;
                    if (y + r.height > innerHeight - pad) y = innerHeight - r.height - pad;
                    if (x < pad) x = pad; if (y < pad) y = pad;
                    popup.style.left = x + 'px'; popup.style.top = y + 'px';
                }
                btnGroupe.addEventListener('click', (e)=>{
                    overlay.style.display='block'; popup.style.display='block';
                    const btnRect = btnGroupe.getBoundingClientRect();
                    let synthetic = (!e || (typeof e.clientX !== 'number') || (e.clientX===0 && e.clientY===0));
                    let x, y;
                    if (synthetic) {
                        if (window.__lastMouseX != null && window.__lastMouseY != null) {
                            x = window.__lastMouseX; y = window.__lastMouseY + 14; // sous curseur
                        } else { // fallback bouton
                            x = btnRect.left; y = btnRect.bottom + 6;
                        }
                    } else {
                        x = e.clientX; y = e.clientY;
                    }
                    openGroupPopupAt(x,y);
                });
                overlay.addEventListener('click', ()=>{ popup.style.display='none'; overlay.style.display='none'; });

                function makeBtn(g){
                    const b=document.createElement('button');
                    b.className='groupe-item';
                    b.setAttribute('data-numero', g.id);
                    b.textContent=g.label;
                    styleGroupBtn(b, g.id === window.NumeroDeGroupeValide);
                    return b;
                }
                function styleGroupBtn(btn, active){
                    if(active){
                        btn.style.cssText='width:100%;box-sizing:border-box;padding:20px;font-size:1.05em;border-radius:14px;border:none;background:linear-gradient(90deg,#16a34a,#15803d);color:#fff;font-weight:700;box-shadow:0 4px 14px #04785788, inset 0 0 0 1px #ffffff55;letter-spacing:.5px;transform:translateY(-2px);transition:.18s;';
                    } else {
                        btn.style.cssText='width:100%;box-sizing:border-box;padding:20px;font-size:1.05em;border-radius:14px;border:none;background:linear-gradient(90deg,#3b82f6,#2563eb);color:#fff;font-weight:600;box-shadow:0 2px 8px #0002;letter-spacing:.5px;transition:.18s;';
                    }
                }
                function render(gDatas){
                    if(!Array.isArray(gDatas)) return; liste.innerHTML='';
                    let layoutCols = 1; if (gDatas.length > 24) layoutCols = 3; else if (gDatas.length > 12) layoutCols = 2;
                    if (layoutCols === 1) {
                        popup.style.minWidth=''; popup.style.width='320px'; liste.style.cssText='display:flex;flex-direction:column;gap:18px;margin-top:16px;';
                        gDatas.forEach(g=>liste.appendChild(makeBtn(g)));
                    } else {
                        popup.style.minWidth='700px'; popup.style.width=''; Object.assign(liste.style,{flexDirection:'row',flexWrap:'wrap',gap:'18px',justifyContent:'center'});
                        const cols = Array.from({ length: layoutCols }, () => { const c=document.createElement('div'); c.style.cssText='display:flex;flex-direction:column;gap:18px;width:320px;'; liste.appendChild(c); return c; });
                        gDatas.forEach((g,i)=>cols[i % layoutCols].appendChild(makeBtn(g)));
                    }
                }
                liste.addEventListener('click',(e)=>{
                    if(!e.target.classList.contains('groupe-item')) return; overlay.style.display='none'; popup.style.display='none';
                    const n=e.target.getAttribute('data-numero'); window.NumeroDeGroupeValide=n; window.filtrerParGroupeNumero(n==='all'?'all':n);
                    // Mettre à jour surbrillance
                    liste.querySelectorAll('.groupe-item').forEach(btn=>styleGroupBtn(btn, btn.getAttribute('data-numero')===window.NumeroDeGroupeValide));
                });
                // Charge initial
                fetch('DatasUnites.php?groupes=1').then(r=>r.json()).then(d=>{
                    window.groupesData = [ { id:'all', label:'Toutes les unités' }, ...(d.groupes||[]).map(g=>({id:g.Id,label:g.Groupe})) ];
                    render(window.groupesData);
                }).catch(()=>{});
                // Mise à jour périodique + détection changement
                let lastHash=null;
                setInterval(()=>{
                    fetch('DatasUnites.php?groupes=1').then(r=>r.ok?r.json():null).then(d=>{
                        if(!d||!Array.isArray(d.groupes)) return; const hash=d.groupes.length+':'+d.groupes.map(g=>g.Id+'-'+g.Groupe).join('|');
                        if(lastHash && hash!==lastHash){ window.groupesData=[ { id:'all', label:'Toutes les unités' }, ...d.groupes.map(g=>({id:g.Id,label:g.Groupe}))]; render(window.groupesData); }
                        if(!lastHash) lastHash=hash; else if(hash!==lastHash) lastHash=hash; // maj hash
                    }).catch(()=>{});
                }, 4000);
            }

            // Menu Pages
            function initMenuPages(){
                const btnPage=document.getElementById('btn-page');
                const popup=document.getElementById('popup-page');
                const overlay=document.getElementById('overlay-page');
                const liste=document.getElementById('page-list');
                if(!btnPage||!popup||!overlay||!liste) return; 
                const pages= (window.topBarPagesOverride) ? window.topBarPagesOverride : [
                    { id:'units', label:'Unités', root:true },
                    { id:'lennox', label:'Lennox', file:'lennox.php', direct:true },
                    { id:'info', label:'Informatique', file:'info.php' }
                ];
                // Ajout de balises prerender pour les pages les plus probables (hors page actuelle)
                (function addPrerender(){
                    const current=location.pathname.toLowerCase();
                    pages.filter(p=>p.file && current.indexOf(p.file.toLowerCase())===-1).forEach(p=>{
                        const id='prer_'+p.file.replace(/[^a-z0-9_\.]+/gi,'_');
                        if(!document.getElementById(id)){
                            const l=document.createElement('link'); l.id=id; l.rel='prerender'; l.href=p.file; document.head.appendChild(l);
                        }
                    });
                })();
                // Pré-chauffage réseau via fetch HEAD/GET (keep-alive socket)
                (function warmConnections(){
                    pages.forEach(p=>{ if(p.file){ try { fetch(p.file, { method:'GET', headers:{'X-Warm':'1'}, cache:'no-store' }).catch(()=>{}); } catch(_){ } } });
                })();
                // Pré-crée des balises prefetch pour accélérer navigation
                function prefetchHref(href){
                    if(!href) return; const id='pf_'+href.replace(/[^a-z0-9_\.]+/gi,'_');
                    if(document.getElementById(id)) return; const l=document.createElement('link'); l.id=id; l.rel='prefetch'; l.href=href; document.head.appendChild(l);
                }
                pages.forEach(p=>{ if(p.file) prefetchHref(p.file); });
                function detectActive(){
                    const path=location.pathname.toLowerCase();
                    if(path.indexOf('lennox.php')!==-1) return 'lennox';
                    if(path.indexOf('index.php')!==-1) return 'units';
                    if(path.indexOf('info.php')!==-1) return 'info';
                    return null;
                }
                function styleBtn(b,active){
                    if(active){
                        b.style.background='linear-gradient(90deg,#16a34a,#15803d)';
                        b.style.boxShadow='0 3px 12px #065f4680, inset 0 0 0 1px #ffffff50';
                        b.style.transform='translateY(-2px)';
                    } else {
                        b.style.background='linear-gradient(90deg,#6366f1,#4f46e5)';
                        b.style.boxShadow='0 2px 8px #0003';
                        b.style.transform='none';
                    }
                }
                btnPage.addEventListener('click',(e)=>{ overlay.style.display='block'; popup.style.display='block'; let x=e.clientX,y=e.clientY; const r=popup.getBoundingClientRect(); const pad=16; if(x+r.width>innerWidth-pad)x=innerWidth-r.width-pad; if(y+r.height>innerHeight-pad)y=innerHeight-r.height-pad; popup.style.left=x+'px'; popup.style.top=y+'px'; });
                overlay.addEventListener('click',()=>{ popup.style.display='none'; overlay.style.display='none'; });
                function showPageTransition(){
                    if(document.getElementById('page-transition-overlay')) return;
                    const div=document.createElement('div');
                    div.id='page-transition-overlay';
                    div.innerHTML='<div style="display:flex;flex-direction:column;align-items:center;">\n <div class="spinner"></div>\n <div class="msg">Ouverture...</div>\n</div>';
                    document.body.appendChild(div);
                    document.body.classList.add('page-transitioning');
                }
                function makeBtn(p){
                    const b=document.createElement('button');
                    b.className='page-item';
                    b.setAttribute('data-page', p.id);
                    b.textContent=p.label;
                    b.style.cssText='text-align:left;width:100%;box-sizing:border-box;padding:18px 14px;font-size:1.05em;border-radius:14px;border:none;color:#fff;font-weight:600;letter-spacing:.5px;transition:.14s;cursor:pointer;';
                    b.addEventListener('mouseenter', ()=>{ if(p.file) prefetchHref(p.file); });
                    // Navigation anticipée sur pointerdown (plus rapide que click)
                    b.addEventListener('pointerdown', (ev)=>{
                        if(p.direct){
                            // Empêche délai potentiel de click + fermeture popup
                            ev.preventDefault(); ev.stopPropagation(); showPageTransition();
                            requestAnimationFrame(()=>{ window.location.href=p.file; });
                        } else if(p.root){
                            ev.preventDefault(); ev.stopPropagation(); if(location.pathname.toLowerCase().indexOf('index.php')!==-1) return; showPageTransition();
                            requestAnimationFrame(()=>{ window.location.href='index.php'; });
                        }
                    }, { passive:false });
                    return b;
                }
                function render(){ const active=detectActive(); liste.innerHTML=''; pages.forEach(p=>{ const b=makeBtn(p); styleBtn(b, p.id===active); liste.appendChild(b); }); }
                render();
                liste.addEventListener('click',(e)=>{ const b=e.target.closest('.page-item'); if(!b) return; const pid=b.getAttribute('data-page'); const p=pages.find(x=>x.id===pid); if(!p) return; popup.style.display='none'; overlay.style.display='none'; if(p.direct||p.root) return; const over=document.getElementById('OverScreenWunites'); const ifr=document.getElementById('OSWunite'); if(over) over.style.display='block'; if(ifr){ if(ifr.src.endsWith(p.file)) { } else { ifr.src=p.file; } } });
            }

            // Lancer init menus après DOM prêt
            function afterMenus(){
                // Exécuter action passée dans l'URL (après init menus)
                const params=new URLSearchParams(location.search);
                const action=params.get('action');
                if(action){
                    if(action==='showGroupMenu'){
                        const pos = readGroupMousePosCookie();
                        clearGroupMousePosCookie();
                        const bg=document.getElementById('btn-groupe');
                        if(pos){
                            // ouvrir directement sans click synthétique
                            const popup=document.getElementById('popup-groupe');
                            const overlay=document.getElementById('overlay-groupe');
                            if(popup && overlay){
                                overlay.style.display='block'; popup.style.display='block';
                                let x=pos.x, y=pos.y+14; const r=popup.getBoundingClientRect(); const pad=16;
                                if (x + r.width > innerWidth - pad) x = innerWidth - r.width - pad;
                                if (y + r.height > innerHeight - pad) y = innerHeight - r.height - pad;
                                if (x < pad) x = pad; if (y < pad) y = pad;
                                popup.style.left=x+'px'; popup.style.top=y+'px';
                            } else if(bg){ setTimeout(()=>bg.click(),40); }
                        } else if(bg){ setTimeout(()=>bg.click(),40); }
                    } else if(action==='actionGroupe') setTimeout(()=>window.ClimGroupe(),40);
                    else if(action==='programmation') setTimeout(()=>window.openProgrammation(),40);
                    try { history.replaceState(null,'', location.pathname); } catch(_){ }
                }
            }
            if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',()=>{ initMenuGroupes(); initMenuPages(); afterMenus(); }); else { initMenuGroupes(); initMenuPages(); afterMenus(); }
        }
    })();
    </script>
</div><!-- Fin FrameTitre -->
