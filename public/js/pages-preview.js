document.addEventListener('DOMContentLoaded', () => {
  const source=document.getElementById('sno-preview-data');
  if(!source)return;
  const data=JSON.parse(source.textContent);
  const route=document.body.dataset.previewRoute;
  const base=document.body.dataset.previewBase||'';
  const params=new URLSearchParams(location.search);
  document.querySelectorAll('form[method="GET"] input, form[method="GET"] select').forEach(field=>{
    if(!field.name||field.type==='hidden'||!params.has(field.name))return;
    if(field.type==='checkbox')field.checked=params.get(field.name)==='1';
    else if(field.type==='radio')field.checked=field.value===params.get(field.name);
    else field.value=params.get(field.name);
  });
  const includes=(value,q)=>String(value||'').toLocaleLowerCase('ru').includes(String(q||'').toLocaleLowerCase('ru'));
  const matchFields=(record,fields)=>fields.every(key=>!params.get(key)||String(record[key])===params.get(key));
  function filter(selector,records,predicate){
    let visible=0;
    document.querySelectorAll(selector+'[data-preview-id]').forEach(card=>{
      const record=records.find(item=>String(item.id)===card.dataset.previewId);
      const show=!!record&&predicate(record);card.hidden=!show;if(show)visible++;
    });
    const count=document.querySelector('.result-count');if(count)count.textContent='Найдено задач: '+visible;
    if(!visible){const empty=document.createElement('div');empty.className='empty-state';const title=document.createElement('h3');title.textContent='Подходящих примеров пока нет';const text=document.createElement('p');text.textContent='Попробуй убрать часть фильтров или изменить запрос.';empty.append(title,text);document.querySelector(selector)?.parentNode.append(empty);}
  }
  if(route==='/research'){
    filter('.task-card',data.tasks,t=>includes(t.title+' '+t.summary,params.get('q'))&&matchFields(t,['direction','level','work_type','participation'])&&(!params.get('hours')||t.hours<=Number(params.get('hours')))&&(!params.get('open')||t.open));
    if(params.get('match')){const note=document.createElement('p');note.className='match-explanation';note.textContent='Примеры подобраны по выбранным направлению, опыту и доступному времени. Это фильтры демонстрационного каталога.';document.querySelector('.filter-panel')?.after(note);}
  }
  if(route==='/mentors')filter('.mentor-card',data.mentors,m=>includes(m.name+' '+m.interests,params.get('q'))&&matchFields(m,['department'])&&(!params.get('beginners')||m.beginners));
  if(route==='/conferences'||route==='/conference-announcements'){
    let shown=0;
    document.querySelectorAll('[data-conference-id]').forEach(card=>{
      const conference=data.events.find(item=>String(item.id)===card.dataset.conferenceId);
      const show=!!conference&&includes(conference.title+' '+conference.description,params.get('q'))&&(route!=='/conference-announcements'||new Date(conference.starts_at)>new Date());
      card.hidden=!show;if(show)shown++;
    });
    if(!shown&&!document.querySelector('[data-conference-list] .empty-state')){
      const empty=document.createElement('p');empty.className='empty-state';empty.textContent='Конференции по этому запросу не найдены.';document.querySelector('[data-conference-list]')?.append(empty);
    }
  }
  document.querySelectorAll('form[data-preview-question]').forEach(form=>{
    const endpoint=form.dataset.previewQuestion;
    const button=form.querySelector('button[type="submit"]');
    const status=form.querySelector('[data-question-status]');
    if(endpoint)button.disabled=false;
    form.addEventListener('submit',async event=>{
      event.preventDefault();
      if(!endpoint||!form.reportValidity())return;
      button.disabled=true;button.setAttribute('aria-busy','true');status.textContent='Отправляем вопрос…';
      try{
        const payload=Object.fromEntries(new FormData(form));
        const response=await fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(payload),credentials:'omit'});
        const result=await response.json();
        if(!response.ok){status.textContent=response.status===422?Object.values(result.errors||{}).flat().join(' '):'Не удалось передать вопрос. Попробуй позже.';return;}
        form.reset();status.textContent=result.message||'Вопрос передан представителям СНО.';
      }catch(error){status.textContent='Связь с сервером недоступна. Вопрос не отправлен. Попробуй позже.';}
      finally{button.disabled=false;button.removeAttribute('aria-busy');}
    });
  });
  if(route==='/events'){
    const events=data.events.filter(e=>!params.get('type')||e.type===params.get('type'));
    document.querySelectorAll('.tabs a').forEach(link=>link.classList.toggle('active',(new URL(link.href).searchParams.get('view')||'list')===(params.get('view')||'list')));
    if(params.get('view')!=='calendar')filter('.event-row',data.events,e=>events.includes(e));
    else {
      const list=document.querySelector('.event-list');if(list)list.hidden=true;
      const raw=params.get('month')||new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Moscow',year:'numeric',month:'2-digit'}).format(new Date());
      const valid=/^\d{4}-(0[1-9]|1[0-2])$/.test(raw)?raw:'2026-10';
      const [year,month]=valid.split('-').map(Number);const first=new Date(Date.UTC(year,month-1,1));
      const panel=document.createElement('section');const header=document.createElement('div');header.className='calendar-head';
      function monthLink(offset,label){const date=new Date(Date.UTC(year,month-1+offset,1));const url=new URL(location.href);url.searchParams.set('month',date.toISOString().slice(0,7));const a=document.createElement('a');a.href=url.pathname+url.search;a.className='button button-outline button-small';a.setAttribute('aria-label',label);a.textContent=offset<0?'‹':'›';return a;}
      const heading=document.createElement('h2');heading.textContent=new Intl.DateTimeFormat('ru-RU',{month:'long',year:'numeric',timeZone:'UTC'}).format(first);header.append(monthLink(-1,'Предыдущий месяц'),heading,monthLink(1,'Следующий месяц'));panel.append(header);
      const calendar=document.createElement('div');calendar.className='calendar';['Пн','Вт','Ср','Чт','Пт','Сб','Вс'].forEach(name=>{const day=document.createElement('div');day.className='weekday';day.textContent=name;calendar.append(day);});
      const padding=(first.getUTCDay()+6)%7;for(let n=0;n<padding;n++){const cell=document.createElement('div');cell.className='calendar-day blank';calendar.append(cell);}
      const days=new Date(Date.UTC(year,month,0)).getUTCDate();for(let day=1;day<=days;day++){
        const cell=document.createElement('div');cell.className='calendar-day';const number=document.createElement('span');number.textContent=day;cell.append(number);
        events.filter(event=>new Intl.DateTimeFormat('sv-SE',{timeZone:'Europe/Moscow'}).format(new Date(event.starts_at))===valid+'-'+String(day).padStart(2,'0')).forEach(event=>{const a=document.createElement('a');a.href=base+'/events/'+event.id+'/';const time=new Intl.DateTimeFormat('ru-RU',{timeZone:'Europe/Moscow',hour:'2-digit',minute:'2-digit'}).format(new Date(event.starts_at));a.textContent=time+' · '+event.title+' (пример)';cell.append(a);});calendar.append(cell);
      }
      panel.append(calendar);list?.before(panel);
    }
  }
});
