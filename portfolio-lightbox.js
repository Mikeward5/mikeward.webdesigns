document.addEventListener("DOMContentLoaded", () => {
  const dialog = document.querySelector(".portfolio-lightbox");
  const dialogImage = document.querySelector(".portfolio-lightbox__image");
  const closeButton = document.querySelector(".portfolio-lightbox__close");
  const enlargeButtons = document.querySelectorAll(".portfolio-card__enlarge");

  // Stop if the lightbox elements are missing
  if (!dialog || !dialogImage || !closeButton) {
    return;
  }

  // Open the correct image when an enlarge button is clicked
  enlargeButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const imageSrc = button.dataset.image;
      const imageAlt = button.dataset.alt || "Website design concept";

      if (!imageSrc) {
        return;
      }

      dialogImage.src = imageSrc;
      dialogImage.alt = imageAlt;

      dialog.showModal();
    });
  });

  // Close using the X button
  closeButton.addEventListener("click", () => {
    dialog.close();
  });

  // Close when clicking outside the dialog
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) {
      const bounds = dialog.getBoundingClientRect();

      const clickedOutside =
        event.clientX < bounds.left ||
        event.clientX > bounds.right ||
        event.clientY < bounds.top ||
        event.clientY > bounds.bottom;

      if (clickedOutside) {
        dialog.close();
      }
    }
  });
});
