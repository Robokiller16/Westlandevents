const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const app = $("#app");
const page = new URLSearchParams(location.search).get("page") || "home";
const isAdminRoute = page === "admin";
const isPortalRoute = page === "portal";
let state = null;
let users = [];
let currentUser = null;
let activeTab = "overview";

const collections = {
  news: { title: "Nieuws", fields: [["title", "Titel"], ["tag", "Label"], ["date", "Datum"], ["body", "Bericht"]] },
  events: { title: "Events", fields: [["title", "Titel"], ["type", "Type"], ["date", "Tijd"], ["host", "Host"], ["status", "Status"]] },
  members: { title: "Leden", fields: [["rsn", "RSN"], ["rank", "Rank"], ["role", "Rol"], ["combat", "Combat"], ["total", "Total"], ["status", "Status"]] },
  loot: { title: "Lootboard", fields: [["item", "Item"], ["player", "Speler"], ["value", "Waarde"], ["date", "Datum"]] }
};

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, char => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[char]));
}

async function api(path, body = null) {
  const options = body ? { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) } : {};
  const response = await fetch(path, options);
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.ok === false) throw new Error(data.error || "Request mislukt");
  return data;
}

function toast(message) {
  const item = document.createElement("div");
  item.className = "toast";
  item.textContent = message;
  document.body.appendChild(item);
  setTimeout(() => item.remove(), 3400);
}

async function load() {
  const data = await api("api/state.php").catch(error => {
    app.innerHTML = `<main class="empty">${escapeHtml(error.message)}</main>`;
    return null;
  });
  if (!data) return;
  state = data.state;
  users = data.users || [];
  currentUser = data.user || null;
  render();
}

function render() {
  if (isAdminRoute) return currentUser ? renderAdmin() : renderLogin();
  if (isPortalRoute) return currentUser ? renderPortal() : renderLogin("Log in om de portal te openen.");
  renderHome();
}

function nav() {
  return `<header class="nav"><a class="brandmark" href="index.php"><img src="public/westland-logo.png" alt="West land logo"><span>West land</span></a><nav><a href="index.php#events">Events</a><a href="index.php#members">Members</a><a href="index.php?page=portal">Login</a><a class="nav-admin" href="index.php?page=admin">Admin</a></nav></header>`;
}

function renderHome() {
  app.innerHTML = `${nav()}<main>
    <section class="hero"><div class="hero-copy"><p class="eyebrow">Old School RuneScape clan</p><h1>${escapeHtml(state.clan.name)}</h1><p>${escapeHtml(state.clan.motto)}</p><div class="hero-actions"><a class="button" href="#apply">Aanmelden</a><a class="button ghost" href="index.php?page=portal">Speler login</a></div></div><div class="crest"><img src="public/westland-logo.png" alt="West land vlag"><dl><div><dt>World</dt><dd>${escapeHtml(state.clan.world)}</dd></div><div><dt>Home</dt><dd>${escapeHtml(state.clan.home)}</dd></div><div><dt>Req</dt><dd>${escapeHtml(state.clan.requirement)}</dd></div></dl></div></section>
    <section class="ticker">${escapeHtml(state.clan.motd)}</section>
    <section class="stats">${state.stats.map(stat => `<article><strong>${escapeHtml(stat.value)}</strong><span>${escapeHtml(stat.label)}</span></article>`).join("")}</section>
    <section class="grid-section" id="events"><div><p class="eyebrow">Planning</p><h2>Clan events</h2></div><div class="cards">${state.events.map(eventCard).join("")}</div></section>
    <section class="split"><div><p class="eyebrow">Nieuws</p><h2>Laatste updates</h2><div class="stack">${state.news.map(newsCard).join("")}</div></div><div><p class="eyebrow">Drops</p><h2>Lootboard</h2><div class="loot-list">${state.loot.map(lootCard).join("")}</div></div></section>
    <section class="grid-section" id="members"><div><p class="eyebrow">Roster</p><h2>Uitgelichte leden</h2></div><div class="member-grid">${state.members.slice(0, 8).map(memberCard).join("")}</div></section>
    <section class="apply" id="apply"><div><p class="eyebrow">Join West land</p><h2>Vraag je login aan</h2><p>Vul je RSN en Discord in. Een admin kan je aanvraag keuren en je toevoegen als recruit.</p></div><form id="applyForm" class="form-card"><label>RSN<input name="rsn" required placeholder="Je OSRS naam"></label><label>Discord<input name="discord" required placeholder="naam#0000"></label><div class="two"><label>Combat<input name="combat" inputmode="numeric" placeholder="126"></label><label>Total level<input name="total" inputmode="numeric" placeholder="2000"></label></div><label>Playstyle<input name="playstyle" placeholder="PvM, skilling, raids, social"></label><label>Bericht<textarea name="message" placeholder="Vertel kort waarom je erbij wil."></textarea></label><button type="submit">Aanvraag versturen</button></form></section>
  </main><footer>West land clan hub voor Old School RuneScape.</footer>`;
  $("#applyForm").onsubmit = submitApplication;
}

function eventCard(event) { return `<article class="card event"><span>${escapeHtml(event.type)}</span><h3>${escapeHtml(event.title)}</h3><p>${escapeHtml(event.date)}</p><small>${escapeHtml(event.host)} · ${escapeHtml(event.status)}</small></article>`; }
function newsCard(item) { return `<article class="line-card"><span>${escapeHtml(item.tag)} · ${escapeHtml(item.date)}</span><h3>${escapeHtml(item.title)}</h3><p>${escapeHtml(item.body)}</p></article>`; }
function lootCard(item) { return `<article><strong>${escapeHtml(item.item)}</strong><span>${escapeHtml(item.player)}</span><b>${escapeHtml(item.value)}</b><small>${escapeHtml(item.date)}</small></article>`; }
function memberCard(member) { return `<article class="member"><strong>${escapeHtml(member.rsn)}</strong><span>${escapeHtml(member.rank)}</span><p>${escapeHtml(member.role)}</p><small>CB ${escapeHtml(member.combat)} · Total ${escapeHtml(member.total)} · ${escapeHtml(member.status)}</small></article>`; }

async function submitApplication(event) {
  event.preventDefault();
  await api("api/application.php", { action: "create", ...Object.fromEntries(new FormData(event.currentTarget)) });
  event.currentTarget.reset();
  toast("Aanvraag verzonden.");
  await load();
}

function renderLogin(message = "") {
  app.innerHTML = `${nav()}<main class="login-page"><form id="loginForm" class="login-card"><img src="public/westland-logo.png" alt="West land logo"><h1>West land login</h1><p>${escapeHtml(message || "Log in voor de portal of het admin beheer.")}</p><label>Gebruikersnaam<input name="username" autocomplete="username" required></label><label>Wachtwoord<input name="password" type="password" autocomplete="current-password" required></label><button type="submit">Inloggen</button></form></main>`;
  $("#loginForm").onsubmit = async event => {
    event.preventDefault();
    await api("api/auth.php", { action: "login", ...Object.fromEntries(new FormData(event.currentTarget)) });
    await load();
  };
}

function renderPortal() {
  app.innerHTML = `${nav()}<main class="portal"><section class="portal-head"><div><p class="eyebrow">Portal</p><h1>Welkom, ${escapeHtml(currentUser.rsn || currentUser.username)}</h1><p>${escapeHtml(state.clan.motd)}</p></div><button class="ghost" id="logout">Uitloggen</button></section><section class="split"><div><h2>Volgende events</h2><div class="cards">${state.events.map(eventCard).join("")}</div></div><div><h2>Clan loot</h2><div class="loot-list">${state.loot.map(lootCard).join("")}</div></div></section></main>`;
  $("#logout").onclick = logout;
}

function renderAdmin() {
  app.innerHTML = `${nav()}<main class="admin"><aside><img src="public/westland-logo.png" alt="West land logo"><strong>${escapeHtml(currentUser.username)}</strong><span>${escapeHtml(currentUser.role)}</span>${["overview", "applications", "news", "events", "members", "loot", "settings", "users"].map(tab => `<button class="${activeTab === tab ? "active" : ""}" data-tab="${tab}">${tabLabel(tab)}</button>`).join("")}<button class="ghost" id="logout">Uitloggen</button></aside><section class="admin-panel">${adminContent()}</section></main>`;
  $$("[data-tab]").forEach(button => button.onclick = () => { activeTab = button.dataset.tab; renderAdmin(); });
  $("#logout").onclick = logout;
  bindAdmin();
}

function tabLabel(tab) { return ({ overview: "Dashboard", applications: "Aanvragen", news: "Nieuws", events: "Events", members: "Leden", loot: "Loot", settings: "Instellingen", users: "Admins" })[tab]; }

function adminContent() {
  if (activeTab === "overview") return `<h1>Dashboard</h1><div class="admin-stats"><article><strong>${state.applications.filter(a => a.status === "pending").length}</strong><span>Open aanvragen</span></article><article><strong>${state.members.length}</strong><span>Leden</span></article><article><strong>${state.events.length}</strong><span>Events</span></article><article><strong>${state.loot.length}</strong><span>Loot drops</span></article></div><h2>Audit</h2><div class="audit">${state.audit.slice(0, 12).map(item => `<p><b>${escapeHtml(item.action)}</b><span>${escapeHtml(item.user)} · ${new Date(item.at).toLocaleString("nl-NL")}</span></p>`).join("")}</div>`;
  if (activeTab === "applications") return `<h1>Aanvragen</h1><div class="stack">${state.applications.map(item => `<article class="request"><div><span>${escapeHtml(item.status)}</span><h3>${escapeHtml(item.rsn)}</h3><p>${escapeHtml(item.discord)} · CB ${escapeHtml(item.combat)} · Total ${escapeHtml(item.total)}</p><small>${escapeHtml(item.playstyle)} - ${escapeHtml(item.message)}</small></div><div><button data-application="${item.id}" data-status="approved">Goedkeuren</button><button class="danger" data-application="${item.id}" data-status="rejected">Afwijzen</button></div></article>`).join("") || "<p>Nog geen aanvragen.</p>"}</div>`;
  if (activeTab === "settings") return `<h1>Clan instellingen</h1><form id="settingsForm" class="admin-form">${["name", "motto", "world", "home", "requirement", "discord", "motd"].map(field => `<label>${field}<input name="${field}" value="${escapeHtml(state.clan[field])}"></label>`).join("")}<button>Opslaan</button></form>`;
  if (activeTab === "users") return currentUser.role !== "owner" ? `<h1>Admins</h1><p>Alleen owners kunnen admins beheren.</p>` : `<h1>Admins</h1><form id="userForm" class="admin-form compact"><input name="username" placeholder="Gebruiker"><input name="rsn" placeholder="RSN"><input name="password" placeholder="Wachtwoord"><select name="role"><option value="admin">Admin</option><option value="owner">Owner</option></select><button>Toevoegen</button></form><div class="stack">${users.map(user => `<article class="row"><strong>${escapeHtml(user.username)}</strong><span>${escapeHtml(user.rsn)} · ${escapeHtml(user.role)}</span><button class="danger" data-user-delete="${user.id}">Verwijder</button></article>`).join("")}</div>`;
  return collectionAdmin(activeTab);
}

function collectionAdmin(name) {
  const config = collections[name];
  return `<h1>${config.title}</h1><form class="admin-form compact" data-collection-form="${name}">${config.fields.map(([field, label]) => `<input name="${field}" placeholder="${label}">`).join("")}<button>Toevoegen</button></form><div class="stack">${state[name].map(item => `<article class="row"><div><strong>${escapeHtml(item.title || item.rsn || item.item)}</strong><span>${config.fields.map(([field]) => escapeHtml(item[field])).filter(Boolean).join(" · ")}</span></div><button class="danger" data-delete="${item.id}" data-collection="${name}">Verwijder</button></article>`).join("")}</div>`;
}

function bindAdmin() {
  $$("[data-application]").forEach(button => button.onclick = () => adminPost("api/application.php", { id: button.dataset.application, status: button.dataset.status }));
  $$("[data-delete]").forEach(button => button.onclick = () => adminPost("api/manage.php", { action: "delete", collection: button.dataset.collection, id: button.dataset.delete }));
  $$("[data-collection-form]").forEach(form => form.onsubmit = event => { event.preventDefault(); adminPost("api/manage.php", { action: "save", collection: form.dataset.collectionForm, item: Object.fromEntries(new FormData(form)) }); });
  const settingsForm = $("#settingsForm");
  if (settingsForm) settingsForm.onsubmit = event => { event.preventDefault(); adminPost("api/settings.php", { clan: Object.fromEntries(new FormData(settingsForm)) }); };
  const userForm = $("#userForm");
  if (userForm) userForm.onsubmit = event => { event.preventDefault(); adminPost("api/users.php", Object.fromEntries(new FormData(userForm))); };
  $$("[data-user-delete]").forEach(button => button.onclick = () => adminPost("api/users.php", { action: "delete", id: button.dataset.userDelete }));
}

async function adminPost(path, body) {
  await api(path, body);
  toast("Opgeslagen");
  await load();
}

async function logout() {
  await api("api/auth.php", { action: "logout" }).catch(() => {});
  location.href = "index.php";
}

load();
