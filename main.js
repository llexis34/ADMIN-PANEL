/* ===========================
   EDIT SETTINGS HERE
   =========================== */
const typingSpeed = 50;
const particleCount = 70;

/* ===========================
   HELPERS
   =========================== */
const $ = (s, root=document) => root.querySelector(s);
const $$ = (s, root=document) => [...root.querySelectorAll(s)];
const clamp = (n, min, max) => Math.max(min, Math.min(max, n));
const smoothstep = (t) => t * t * (3 - 2 * t);

/* ===========================
   TYPEWRITER (kept)
   =========================== */
function typeWriter(text, el, speed){
  if(!el) return;
  let i = 0;
  el.textContent = "";
  el.style.whiteSpace = "pre-line";
  (function type(){
    if(i < text.length){
      el.textContent += text.charAt(i++);
      setTimeout(type, speed);
    }
  })();
}

/* ===========================
   PARTICLES (kept)
   =========================== */
const canvas = $("#particle-canvas");
const ctx = canvas?.getContext("2d");
let particles = [];

function resizeCanvas(){
  if(!canvas) return;
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
}
window.addEventListener("resize", resizeCanvas);

class Particle{
  constructor(){
    this.x = Math.random() * canvas.width;
    this.y = Math.random() * canvas.height;
    this.size = Math.random() * 1.5 + 0.5;
    this.vx = Math.random() * 0.3 - 0.15;
    this.vy = Math.random() * 0.3 - 0.15;
    this.a  = Math.random() * 0.4;
  }
  update(){
    this.x += this.vx; this.y += this.vy;
    if(this.x > canvas.width) this.x = 0;
    if(this.x < 0) this.x = canvas.width;
    if(this.y > canvas.height) this.y = 0;
    if(this.y < 0) this.y = canvas.height;
  }
  draw(){
    ctx.fillStyle = `rgba(255,255,255,${this.a})`;
    ctx.beginPath();
    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
    ctx.fill();
  }
}

function initParticles(){
  if(!canvas || !ctx) return;
  particles = Array.from({length: particleCount}, () => new Particle());
}
function animateParticles(){
  if(!canvas || !ctx) return;
  ctx.clearRect(0,0,canvas.width,canvas.height);
  particles.forEach(p => { p.update(); p.draw(); });
  requestAnimationFrame(animateParticles);
}

/* ===========================
   REVEAL SYSTEMS (REPLAY)
   =========================== */
function makeReplayObserver(selector, {threshold=0.18, rootMargin="0px 0px -10% 0px"} = {}, onEnter){
  const targets = $$(selector);
  if(!targets.length) return;

  const obs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if(entry.isIntersecting){
        entry.target.classList.remove("in-view");
        requestAnimationFrame(() => {
          entry.target.classList.add("in-view");
          onEnter?.(entry.target);
        });
      } else {
        entry.target.classList.remove("in-view");
      }
    });
  }, {threshold, rootMargin});

  targets.forEach(t => obs.observe(t));
}

function applyAboutStagger(){
  const aboutContent = $(".about-content");
  if(!aboutContent) return;
  $$(".about-anim", aboutContent).forEach((el, idx) => {
    el.style.transitionDelay = `${120 + idx * 110}ms`;
  });
}

function applySectionChildStagger(section){
  const kids = $$(".reveal-child", section);
  kids.forEach((kid, idx) => {
    kid.style.transitionDelay = `${120 + idx * 110}ms`;
  });
}

/* ===========================
   ABOUT IMAGE SCROLL MOTION (kept)
   =========================== */
function setupAboutImageMotion(){
  const prefersReduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const aboutSection = $("#about");
  const aboutImg = $(".about-image");
  if(!aboutSection || !aboutImg) return;

  const parent = aboutImg.parentElement;
  if(parent?.classList.contains("about-image-frame")) parent.classList.remove("about-image-frame");

  let ticking = false;

  const update = () => {
    ticking = false;
    if(prefersReduced) return;

    const rect = aboutSection.getBoundingClientRect();
    const vh = window.innerHeight || document.documentElement.clientHeight;

    const startPoint = vh * 0.95;
    const endPoint   = vh * 0.55;

    const raw = (startPoint - rect.top) / (startPoint - endPoint);
    const t = clamp(raw, 0, 1);
    const ease = smoothstep(t);

    const from = { x:240, y:140, r:10, o:0 };
    const to   = { x:0,   y:0,   r:0,  o:1 };

    const x = (from.x + (to.x - from.x) * ease).toFixed(2);
    const y = (from.y + (to.y - from.y) * ease).toFixed(2);
    const r = (from.r + (to.r - from.r) * ease).toFixed(2);
    const o = (from.o + (to.o - from.o) * ease).toFixed(3);

    aboutImg.style.setProperty("--about-enter-x", `${x}px`);
    aboutImg.style.setProperty("--about-enter-y", `${y}px`);
    aboutImg.style.setProperty("--about-enter-rot", `${r}deg`);
    aboutImg.style.setProperty("--about-enter-opacity", `${o}`);

    const imgRect = aboutImg.getBoundingClientRect();
    const center = imgRect.top + imgRect.height/2;
    const delta = (center - vh/2) / (vh/2);
    const max = 14;
    const offset = clamp(-delta * max, -max, max);
    aboutImg.style.setProperty("--about-parallax", `${offset}px`);
  };

  const onScroll = () => {
    if(!ticking){
      ticking = true;
      requestAnimationFrame(update);
    }
  };

  update();
  window.addEventListener("scroll", onScroll, {passive:true});
  window.addEventListener("resize", update);
}

/* ===========================
   HERO BUTTON RIPPLE (kept)
   =========================== */
function setupHeroButton(){
  const heroButton = $(".btn-primary-custom");
  if(!heroButton) return;

  heroButton.addEventListener("click", function(e){
    const rect = this.getBoundingClientRect();
    const ripple = document.createElement("span");
    const size = Math.max(rect.width, rect.height);

    ripple.style.width = ripple.style.height = size + "px";
    ripple.style.left = (e.clientX - rect.left - size/2) + "px";
    ripple.style.top  = (e.clientY - rect.top  - size/2) + "px";
    ripple.className = "ripple-effect";

    this.appendChild(ripple);
    setTimeout(() => ripple.remove(), 600);
  });

  heroButton.addEventListener("mouseenter", () => heroButton.style.transform = "scale(1.07)");
  heroButton.addEventListener("mouseleave", () => heroButton.style.transform = "");
}

/* ===========================
   PRODUCTS SLIDER (kept)
   =========================== */
function setupProductSlider(){
  const track = $(".product-track");
  const slides = $$(".product-slide");
  const btnLeft  = $(".product-arrow.left");
  const btnRight = $(".product-arrow.right");
  if(!track || !slides.length || !btnLeft || !btnRight) return;

  const perView = () => (window.innerWidth <= 768 ? 1 : window.innerWidth <= 992 ? 2 : 3);
  let index = 0;

  const maxIndex = () => Math.max(0, Math.ceil(slides.length / perView()) - 1);
  const apply = () => track.style.transform = `translateX(-${index * 100}%)`;

  const clampIndex = () => {
    const m = maxIndex();
    if(index < 0) index = m;
    if(index > m) index = 0;
  };

  btnRight.addEventListener("click", () => { index++; clampIndex(); apply(); });
  btnLeft.addEventListener("click", () => { index--; clampIndex(); apply(); });

  window.addEventListener("resize", () => {
    const m = maxIndex();
    if(index > m) index = m;
    apply();
  });

  apply();
}

/* ===========================
   ✅ LOANS SHOWCASE (Filter ONLY — MODAL REMOVED)
   =========================== */
function setupLoansShowcase(){
  const filters = $$(".loan-filter");
  const cards = $$(".loan-card");

  const setActive = (btn) => {
    filters.forEach(b => b.classList.remove("is-active"));
    btn.classList.add("is-active");
  };

  const applyFilter = (key) => {
    cards.forEach(card => {
      const cat = card.getAttribute("data-cat");
      const show = (key === "all") || (cat === key);
      card.classList.toggle("is-hidden", !show);
    });
  };

  if(filters.length && cards.length){
    filters.forEach(btn => {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        const key = btn.getAttribute("data-filter") || "all";
        setActive(btn);
        applyFilter(key);
      });
    });

    applyFilter("all");
  }

  /* ✅ IMPORTANT: disable any old modal click behavior safely (no design break) */
  if(cards.length){
    cards.forEach(card => {
      // make cards non-interactive in JS (CSS hover still works)
      card.style.cursor = "default";
      card.setAttribute("aria-disabled", "true");
      card.setAttribute("tabindex", "-1");
    });
  }
}

/* ===========================
   DOM READY
   =========================== */
window.addEventListener("DOMContentLoaded", () => {
  if(!$(".bg-zoom-container")){
    const bg = document.createElement("div");
    bg.className = "bg-zoom-container";
    document.body.prepend(bg);
  }

  if(canvas && ctx){
    resizeCanvas();
    initParticles();
    animateParticles();
  }

  const hero = $(".hero-container");
  if(hero) setTimeout(() => hero.classList.add("enter"), 120);
  typeWriter("LIMCOMA MULTI-PURPOSE COOPERATIVE", $("#typing-effect"), typingSpeed);

  makeReplayObserver(".reveal-on-scroll", {threshold:0.18, rootMargin:"0px 0px -10% 0px"}, (el) => {
    if(el.classList.contains("about-content")) applyAboutStagger();
  });

  makeReplayObserver(".section-reveal", {threshold:0.16, rootMargin:"0px 0px -12% 0px"}, (section) => {
    applySectionChildStagger(section);
  });

  applyAboutStagger();
  setupAboutImageMotion();
  setupHeroButton();
  setupProductSlider();
  setupLoansShowcase();
});