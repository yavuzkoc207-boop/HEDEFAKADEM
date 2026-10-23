(() => {
  'use strict';

  const VERSION = '2026.08.31.5.0-cartoon-ai';
  const OFFSETS = [0, 72, 138, 72, 0, -72, -138, -72];
  let scheduled = false;


  function ensureCartoonFilter() {
    if (document.getElementById('ha-route-cartoon-svg')) return;
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.id = 'ha-route-cartoon-svg';
    svg.setAttribute('width', '0');
    svg.setAttribute('height', '0');
    svg.setAttribute('aria-hidden', 'true');
    svg.style.position = 'absolute';
    svg.style.pointerEvents = 'none';
    svg.innerHTML = `<defs>
      <filter id="ha-route-cartoon-filter" x="-8%" y="-8%" width="116%" height="116%" color-interpolation-filters="sRGB">
        <feGaussianBlur stdDeviation="0.34" result="soft"/>
        <feColorMatrix in="soft" type="saturate" values="1.42" result="sat"/>
        <feComponentTransfer in="sat" result="poster">
          <feFuncR type="discrete" tableValues="0 .17 .34 .51 .68 .85 1"/>
          <feFuncG type="discrete" tableValues="0 .17 .34 .51 .68 .85 1"/>
          <feFuncB type="discrete" tableValues="0 .17 .34 .51 .68 .85 1"/>
        </feComponentTransfer>
        <feColorMatrix in="poster" type="matrix" values="1.06 0 0 0 .01  0 1.04 0 0 .01  0 0 1.03 0 .02  0 0 0 1 0"/>
      </filter>
    </defs>`;
    document.body.appendChild(svg);
  }

  function scheduleSync() {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
      scheduled = false;
      syncExperience();
    });
  }

  function cleanText(value) {
    return (value || '').replace(/\s+/g, ' ').trim();
  }

  function renameNavigation() {
    document.querySelectorAll('.nav-item').forEach((button) => {
      const labels = [...button.querySelectorAll('span')];
      const label = labels.find((el) => cleanText(el.textContent) === 'Dersler');
      if (label) {
        label.textContent = 'Öğrenme Rotası';
        const icon = button.querySelector('.nav-icon');
        if (icon) icon.textContent = '↟';
        button.setAttribute('aria-label', 'Öğrenme Rotası');
      }
    });

    document.querySelectorAll('.bottom-nav button').forEach((button) => {
      const small = button.querySelector('small');
      if (small && cleanText(small.textContent) === 'Dersler') {
        small.textContent = 'Rota';
        const icon = button.querySelector('span');
        if (icon) icon.textContent = '↟';
      }
    });

    const routePage = document.querySelector('.journey-v3');
    if (routePage) {
      const topTitle = document.querySelector('.topbar .heading h1');
      if (topTitle && cleanText(topTitle.textContent) === 'Dersler') {
        topTitle.textContent = 'Öğrenme Rotası';
      }
    }
  }

  function cloneLevelSwitcher(page) {
    const original = page.querySelector('.level-switcher');
    if (!original) return null;

    const shell = document.createElement('div');
    shell.className = 'ha-level-switcher';

    [...original.querySelectorAll('button')].forEach((button) => {
      const clone = document.createElement('button');
      clone.type = 'button';
      clone.disabled = button.disabled;
      clone.className = button.classList.contains('active') ? 'active' : '';
      clone.innerHTML = button.innerHTML;
      clone.addEventListener('click', () => {
        if (!button.disabled) button.click();
      });
      shell.appendChild(clone);
    });

    return shell;
  }

  function routeSignature(page) {
    const lessonText = [...page.querySelectorAll('.unit-list .course-row')]
      .map((button) => `${button.className}:${cleanText(button.textContent)}`)
      .join('|');
    const level = cleanText(page.querySelector('.journey-header h2')?.textContent);
    return `${level}::${lessonText}`;
  }

  function createNode(button, lessonIndex) {
    const status = ['done', 'current', 'open', 'locked'].find((name) => button.classList.contains(name)) || 'open';
    const title = cleanText(button.querySelector('strong')?.textContent) || `Ders ${lessonIndex + 1}`;
    const meta = cleanText(button.querySelector('span')?.textContent);
    const summary = cleanText(button.querySelector('small')?.textContent);
    const sourceIcon = cleanText(button.querySelector('i')?.textContent);
    const action = cleanText(button.querySelector('em')?.textContent).replace('→', '').trim();

    const wrap = document.createElement('div');
    wrap.className = `ha-route-node-wrap ${status}`;
    wrap.style.setProperty('--route-offset', `${OFFSETS[lessonIndex % OFFSETS.length]}px`);

    const node = document.createElement('button');
    node.type = 'button';
    node.className = `ha-route-node ${status}`;
    node.disabled = status === 'locked';
    node.setAttribute('aria-label', `${title} — ${action || 'Dersi aç'}`);

    const glyph = status === 'done' ? '✓' : status === 'locked' ? '⌁' : sourceIcon || '▶';
    node.innerHTML = `<span class="ha-node-core"><b>${glyph}</b></span>`;
    node.addEventListener('click', () => {
      if (!button.disabled) button.click();
    });

    const card = document.createElement('div');
    card.className = 'ha-route-node-card';
    card.innerHTML = `
      <small>${meta || `DERS ${lessonIndex + 1}`}</small>
      <strong>${title}</strong>
      ${summary ? `<p>${summary}</p>` : ''}
      <span>${status === 'done' ? 'Tamamlandı' : status === 'current' ? 'Şimdi buradasın' : status === 'open' ? 'Başlamaya hazır' : 'Önceki dersi tamamla'}</span>
    `;

    wrap.append(node, card);
    return wrap;
  }

  function buildRoutePage(page) {
    const unitList = page.querySelector('.unit-list');
    if (!unitList) return;

    const signature = routeSignature(page);
    if (page.dataset.haRouteSignature === signature && page.querySelector('.ha-route-content')) return;

    page.querySelector('.ha-route-content')?.remove();
    page.dataset.haRouteSignature = signature;
    page.classList.add('ha-route-page');

    const content = document.createElement('div');
    content.className = 'ha-route-content';

    const headerTitle = cleanText(page.querySelector('.journey-header h2')?.textContent) || 'Öğrenme Rotası';
    const headerSummary = cleanText(page.querySelector('.journey-header p')?.textContent) || 'Her durak yeni bir beceriyi açar.';
    const progress = cleanText(page.querySelector('.route-total strong')?.textContent) || '0%';
    const completed = cleanText(page.querySelector('.route-total small')?.textContent) || '';

    const hero = document.createElement('header');
    hero.className = 'ha-route-hero';
    hero.innerHTML = `
      <div class="ha-route-hero-copy">
        <span class="ha-route-kicker">ALANYA'DAN DÜNYAYA · HEDEFAKADEMİ</span>
        <h2>${headerTitle}</h2>
        <p>${headerSummary}</p>
        <div class="ha-route-location"><i>⌖</i><span>Akdeniz ile buluşan Alanya Kalesi · Her ders yeni bir durak</span></div>
      </div>
      <div class="ha-route-progress-card">
        <small>ROTA İLERLEMESİ</small>
        <strong>${progress}</strong>
        <span>${completed}</span>
        <i><b style="width:${progress.replace(/[^0-9]/g, '') || 0}%"></b></i>
      </div>
    `;
    content.appendChild(hero);

    const levelSwitcher = cloneLevelSwitcher(page);
    if (levelSwitcher) content.appendChild(levelSwitcher);

    const intro = document.createElement('div');
    intro.className = 'ha-route-intro';
    intro.innerHTML = `
      <span>ÖĞRENME YOLCULUĞUN</span>
      <h3>Dersleri sırayla tamamla, rotada ilerle.</h3>
      <p>Her düğüm kısa bir görev. Tamamladıkça yeni duraklar açılır; bitirdiğin derslere istediğin zaman geri dönebilirsin.</p>
    `;
    content.appendChild(intro);

    const track = document.createElement('div');
    track.className = 'ha-route-track';

    const originalUnits = [...unitList.querySelectorAll('.unit-card-v3')];
    let globalLessonIndex = 0;

    originalUnits.forEach((unit, unitIndex) => {
      const section = document.createElement('section');
      section.className = 'ha-route-unit';

      const number = String(unitIndex + 1).padStart(2, '0');
      const title = cleanText(unit.querySelector('header h3')?.textContent) || `Ünite ${unitIndex + 1}`;
      const summary = cleanText(unit.querySelector('header p')?.textContent);
      const percent = cleanText(unit.querySelector('header aside strong')?.textContent) || '0%';
      const state = cleanText(unit.querySelector('header span')?.textContent) || 'YENİ';

      const unitHeader = document.createElement('div');
      unitHeader.className = 'ha-route-unit-header';
      unitHeader.innerHTML = `
        <div class="ha-route-unit-badge"><small>ÜNİTE</small><strong>${number}</strong></div>
        <div class="ha-route-unit-copy"><span>${state}</span><h3>${title}</h3>${summary ? `<p>${summary}</p>` : ''}</div>
        <div class="ha-route-unit-progress"><strong>${percent}</strong><small>tamamlandı</small></div>
      `;
      section.appendChild(unitHeader);

      const nodes = document.createElement('div');
      nodes.className = 'ha-route-nodes';

      [...unit.querySelectorAll('.course-row')].forEach((button) => {
        nodes.appendChild(createNode(button, globalLessonIndex));
        globalLessonIndex += 1;
      });

      section.appendChild(nodes);
      track.appendChild(section);
    });

    content.appendChild(track);

    const footer = document.createElement('footer');
    footer.className = 'ha-route-footer';
    footer.innerHTML = `
      <div><span>🏰</span><strong>Alanya'da başlayan rota, akıcı konuşmaya kadar devam eder.</strong></div>
      <small>Arka plan: Alanya from sea panorama · © kallerna · CC BY-SA 3.0 / Wikimedia Commons</small>
    `;
    content.appendChild(footer);

    page.insertBefore(content, unitList);
  }

  function syncExperience() {
    renameNavigation();
    const page = document.querySelector('.journey-v3');
    document.documentElement.classList.toggle('ha-route-active', Boolean(page));
    if (page) buildRoutePage(page);
  }

  function boot() {
    document.documentElement.dataset.haRouteVersion = VERSION;
    ensureCartoonFilter();
    syncExperience();
    const root = document.getElementById('root') || document.body;
    const observer = new MutationObserver(scheduleSync);
    observer.observe(root, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['class', 'disabled'] });
    window.addEventListener('popstate', scheduleSync);
    window.setInterval(syncExperience, 1800);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
  else boot();
})();
