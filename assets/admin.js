(function(){
  const form=document.getElementById('articleForm'); if(!form) return;
  const title=document.getElementById('articleTitle'), slug=document.getElementById('articleSlug'), seo=document.getElementById('seoTitle'), desc=document.getElementById('metaDescription'), content=document.getElementById('articleContent');
  let slugTouched=!!(slug&&slug.value);
  const slugify=s=>s.toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g,'').replace(/&/g,' and ').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,100);
  if(slug){slug.addEventListener('input',()=>{slugTouched=true;updateSerp();});}
  if(title){title.addEventListener('input',()=>{if(slug&&!slugTouched)slug.value=slugify(title.value);if(seo&&!seo.value)document.getElementById('serpTitle').textContent=title.value;updateSerp();});}
  function updateCount(id){const el=document.getElementById(id), out=document.querySelector('[data-count-for="'+id+'"]');if(el&&out)out.textContent=el.value.length+' / '+(el.maxLength>0?el.maxLength:'-');}
  function updateSerp(){if(!seo||!desc||!slug)return;document.getElementById('serpTitle').textContent=seo.value||title.value||'Article title';document.getElementById('serpDesc').textContent=desc.value||'Add a useful meta description for this article.';document.getElementById('serpUrl').textContent='learnpianokeys.com/guides/'+(slug.value||'article-slug')+'/';updateCount('seoTitle');updateCount('metaDescription');}
  [seo,desc].forEach(el=>el&&el.addEventListener('input',updateSerp));updateSerp();
  document.querySelectorAll('[data-confirm]').forEach(b=>b.addEventListener('click',e=>{if(!confirm(b.dataset.confirm))e.preventDefault();}));
  function replaceSelection(prefix,suffix){if(!content)return;const start=content.selectionStart,end=content.selectionEnd,selected=content.value.slice(start,end);content.setRangeText(prefix+selected+(suffix||''),start,end,'end');content.focus();}
  document.querySelectorAll('[data-wrap]').forEach(b=>b.addEventListener('click',()=>replaceSelection(b.dataset.wrap,b.dataset.wrap)));
  document.querySelectorAll('[data-prefix]').forEach(b=>b.addEventListener('click',()=>{if(!content)return;const start=content.selectionStart,lineStart=content.value.lastIndexOf('\n',start-1)+1;content.setRangeText(b.dataset.prefix,lineStart,lineStart,'end');content.focus();}));
  const linkBtn=document.querySelector('[data-link]');if(linkBtn)linkBtn.addEventListener('click',()=>{const url=prompt('Link URL (for example /scales.html):');if(!url)return;const selected=content.value.slice(content.selectionStart,content.selectionEnd)||'link text';replaceSelection('['+selected+']('+url+')','');});
})();
