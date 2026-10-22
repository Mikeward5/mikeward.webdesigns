const button = document.querySelector(".menu-toggle");
const nav = document.querySelector(".site-nav__menu");

button.addEventListener("click", () => {
  nav.classList.toggle("is-open");
  let isOpen = nav.classList.contains();
  button.setAttribute("aria-expanded", isOpen);
});
