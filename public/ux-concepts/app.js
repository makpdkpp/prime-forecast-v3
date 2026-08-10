const tabs = document.querySelectorAll('.concept-tab');
const prototypes = document.querySelectorAll('.prototype');
const intros = document.querySelectorAll('.concept-intro');
const deviceButtons = document.querySelectorAll('.device-button');
const stage = document.querySelector('.prototype-stage');

tabs.forEach((tab) => {
  tab.addEventListener('click', () => {
    const concept = tab.dataset.concept;
    tabs.forEach((item) => item.classList.toggle('active', item === tab));
    prototypes.forEach((item) => item.classList.toggle('active', item.dataset.prototype === concept));
    intros.forEach((item) => item.classList.toggle('active', item.dataset.panel === concept));
  });
});

deviceButtons.forEach((button) => {
  button.addEventListener('click', () => {
    deviceButtons.forEach((item) => item.classList.toggle('active', item === button));
    stage.dataset.deviceView = button.dataset.device;
  });
});

document.querySelectorAll('.view-tabs button').forEach((button) => {
  button.addEventListener('click', () => {
    document.querySelectorAll('.view-tabs button').forEach((item) => item.classList.remove('active'));
    button.classList.add('active');
  });
});

const salesScreenTitles = {
  dashboard: '/ ภาพรวมของฉัน',
  create: '/ เพิ่มโครงการใหม่',
  projects: '/ โครงการของฉัน',
  edit: '/ แก้ไขโครงการ',
  profile: '/ โปรไฟล์'
};

function showSalesScreen(screen) {
  document.querySelectorAll('.sales-screen').forEach((item) => {
    item.classList.toggle('active', item.dataset.salesScreen === screen);
  });
  document.querySelectorAll('.focus-prototype [data-sales-nav]').forEach((item) => {
    item.classList.toggle('selected', item.dataset.salesNav === screen || (screen === 'edit' && item.dataset.salesNav === 'projects'));
  });
  const title = document.querySelector('[data-sales-title]');
  if (title) title.textContent = salesScreenTitles[screen] || '';
  const draftAction = document.querySelector('.sales-draft-action');
  if (draftAction) draftAction.style.display = screen === 'create' ? '' : 'none';
  document.querySelector('.focus-prototype .app-page')?.scrollTo({ top: 0, behavior: 'smooth' });
}

document.querySelectorAll('.focus-prototype [data-sales-nav]').forEach((item) => {
  item.addEventListener('click', () => showSalesScreen(item.dataset.salesNav));
});
