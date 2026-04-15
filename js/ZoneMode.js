export class ZoneMode {
  #active = false;
  #radius = 150;
  #canvas;
  #cursor;

  constructor(canvas, cursorEl) {
    this.#canvas = canvas;
    this.#cursor = cursorEl;
  }

  toggle() {
    this.#active = !this.#active;
    this.#canvas.style.cursor = this.#active ? "none" : "default";
    if (!this.#active) this.#cursor.style.display = "none";
    return this.#active;
  }

  moveCursor(clientX, clientY) {
    const rect = this.#canvas.getBoundingClientRect();
    const displayRadius = this.#radius * (rect.width / this.#canvas.width);
    const size = displayRadius * 2;
    this.#cursor.style.display = "block";
    this.#cursor.style.left   = `${clientX - displayRadius}px`;
    this.#cursor.style.top    = `${clientY - displayRadius}px`;
    this.#cursor.style.width  = `${size}px`;
    this.#cursor.style.height = `${size}px`;
  }

  hideCursor() {
    this.#cursor.style.display = "none";
  }

  toCanvasCoords(clientX, clientY) {
    const rect = this.#canvas.getBoundingClientRect();
    return {
      cx: Math.round((clientX - rect.left) * (this.#canvas.width  / rect.width)),
      cy: Math.round((clientY - rect.top)  * (this.#canvas.height / rect.height)),
    };
  }

  set radius(v) { this.#radius = v; }
  get radius()  { return this.#radius; }
  get active()  { return this.#active; }
}
