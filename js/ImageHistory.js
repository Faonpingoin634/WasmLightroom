export class ImageHistory {
  #undoStack = [];
  #redoStack = [];
  #maxSize;

  constructor(maxSize = 20) {
    this.#maxSize = maxSize;
  }

  push(snapshot) {
    this.#undoStack.push(snapshot);
    if (this.#undoStack.length > this.#maxSize) this.#undoStack.shift();
    this.#redoStack = [];
  }

  undo(current) {
    if (!this.#undoStack.length) return null;
    this.#redoStack.push(current);
    return this.#undoStack.pop();
  }

  redo(current) {
    if (!this.#redoStack.length) return null;
    this.#undoStack.push(current);
    return this.#redoStack.pop();
  }

  get canUndo() {
    return this.#undoStack.length > 0;
  }

  get canRedo() {
    return this.#redoStack.length > 0;
  }

  clear() {
    this.#undoStack = [];
    this.#redoStack = [];
  }
}
