document.addEventListener('DOMContentLoaded', () => {
 const toggle=document.querySelector('.menu-toggle');
 const nav=document.querySelector('#main-nav');
 if(toggle&&nav){toggle.addEventListener('click',()=>{const open=toggle.getAttribute('aria-expanded')!=='true';toggle.setAttribute('aria-expanded',String(open));nav.classList.toggle('is-open',open);});document.addEventListener('keydown',e=>{if(e.key==='Escape'){nav.classList.remove('is-open');toggle.setAttribute('aria-expanded','false');toggle.focus();}});}
 document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!confirm(form.dataset.confirm))e.preventDefault();}));
 document.querySelectorAll('form[method="POST"]').forEach(form=>form.addEventListener('submit',()=>{if(form.checkValidity())setTimeout(()=>{form.querySelectorAll('button[type="submit"]').forEach(b=>{b.disabled=true;b.setAttribute('aria-busy','true');});},0);}));
});
