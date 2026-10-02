(() => {
  const stage = document.getElementById('home-arcade-stage');
  if (!stage) return;
  const slides = [...stage.querySelectorAll('.home-arcade__slide')];
  const dots = [...document.querySelectorAll('.home-arcade__dots button')];
  if (slides.length < 2 || dots.length !== slides.length) return;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0;
  let timer;
  const show = index => {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      const active = i === current;
      slide.classList.toggle('is-active', active);
      slide.tabIndex = active ? 0 : -1;
      slide.setAttribute('aria-hidden', active ? 'false' : 'true');
      dots[i].classList.toggle('is-active', active);
      if (active) dots[i].setAttribute('aria-current', 'true');
      else dots[i].removeAttribute('aria-current');
    });
  };
  const stop = () => { if (timer) window.clearInterval(timer); timer = undefined; };
  const start = () => {
    stop();
    if (!reduceMotion.matches && !document.hidden) timer = window.setInterval(() => show(current + 1), 4800);
  };
  dots.forEach((dot, index) => dot.addEventListener('click', () => { show(index); start(); }));
  stage.addEventListener('mouseenter', stop);
  stage.addEventListener('mouseleave', start);
  stage.addEventListener('focusin', stop);
  stage.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
  reduceMotion.addEventListener?.('change', start);
  show(0);
  start();
})();
