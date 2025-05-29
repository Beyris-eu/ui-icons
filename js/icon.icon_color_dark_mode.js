/**
 * @file
 * JavaScript behavior to update UI Icons autocomplete previews with the correct
 * accent color in Drupal Gin dark mode.
 */
(($, Drupal, once) => {
  /**
   * Convert a "rgb(r, g, b)" or "rgba(r, g, b, a)" string to a hex color string.
   * Returns null if the input format is invalid.
   * Clamps each RGB component between 0 and 255 before conversion.
   *
   * @param {string} rgb - The RGB(A) color string.
   * @return {string|null} - Hex color string like "#rrggbb" or null if invalid.
   */
  const rgbToHex = (rgb) => {
    const match = rgb.match(
      /rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i,
    );
    if (!match) return null;
    return `#${match
      .slice(1, 4)
      .map((v) =>
        Math.min(255, Math.max(0, Number(v)))
          .toString(16)
          .padStart(2, "0"),
      )
      .join("")}`;
  };

  // Drupal behavior to modify icon preview colors in Gin dark mode.
  Drupal.behaviors.IconColorModifier = {
    /**
     * Attach behavior to modify icon preview colors based on accent color in dark mode.
     * - Runs on the provided context.
     * - Converts accent color CSS variable to hex.
     * - Updates all existing icon-preview images in context to include color param.
     * - Observes dynamically added autocomplete icons to update their color param.
     *
     * @param {HTMLElement} context - The DOM subtree this behavior attaches to.
     */
    attach(context) {
      requestAnimationFrame(() => {
        // Only proceed if dark mode is active
        if (!document.documentElement.classList.contains("gin--dark-mode"))
          return;

        const accentEl = document.querySelector("[data-gin-accent]");
        if (!accentEl) return;

        // Get primary accent color and convert to hex
        const hex = rgbToHex(
          getComputedStyle(accentEl)
            .getPropertyValue("--gin-color-primary")
            .trim(),
        );
        if (!hex) return;

        // Update existing icon-preview images in the current context
        once("modifyIconPreviewColor", ".icon-preview", context).forEach(
          (img) => {
            try {
              const url = new URL(img.src);
              url.searchParams.set("color", hex);
              img.src = url.toString();
            } catch {
              console.warn("IconColorModifier: invalid icon src URL");
            }
          },
        );

        // Setup observer for dynamically added autocomplete icon-preview images
        once("observeAutocompleteIcons", "body", context).forEach(() => {
          new MutationObserver(() => {
            document
              .querySelectorAll("ul.ui-autocomplete li img.icon-preview")
              .forEach((img) => {
                try {
                  const url = new URL(img.src);
                  url.searchParams.set("color", hex);
                  img.src = url.toString();
                } catch {
                  console.warn(
                    "IconColorModifier (autocomplete): invalid image src URL",
                  );
                }
              });
          }).observe(document.body, { childList: true, subtree: true });
        });
      });
    },
  };
})(jQuery, Drupal, once);
