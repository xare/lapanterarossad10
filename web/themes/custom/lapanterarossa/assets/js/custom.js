 document.addEventListener('DOMContentLoaded', () => {
    const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
    if ($navbarBurgers.length > 0) {
        $navbarBurgers.forEach( el => {
          el.addEventListener('click', () => {
            const target = el.dataset.target;
            const $target = document.getElementById(target);
            el.classList.toggle('is-active');
            $target.classList.toggle('is-active');
          });
        });
    }
    const closeButton = document.querySelector("[data-modal-close]");
    const modal = document.querySelector("[data-modal]");
    if(null !== modal) {
      modal.showModal();
      if(closeButton)
        closeButton.addEventListener('click', () => {
          modal.close();
        });
      modal.addEventListener("click", event => {
        console.info(event);
        const modalDimensions = modal.getBoundingClientRect()
        if (
          event.clientX < modalDimensions.left ||
          event.clientX > modalDimensions.right ||
          event.clientY < modalDimensions.top ||
          event.clientY > modalDimensions.bottom
        ) {
          modal.close()
        }
      });
    }
});