export class History {
  #undo = [];
  #redo = [];
  #max;

  constructor(max = 20) {
    this.#max = max;
  }

  push(snapshot) {
    this.#undo.push(snapshot);
    if (this.#undo.length > this.#max) this.#undo.shift();
    this.#redo = [];
  }

  undo(current) {
    if (!this.#undo.length) return null;
    this.#redo.push(current);
    return this.#undo.pop();
  }

  redo(current) {
    if (!this.#redo.length) return null;
    this.#undo.push(current);
    return this.#redo.pop();
  }

  clear() {
    this.#undo = [];
    this.#redo = [];
  }

  get canUndo() { return this.#undo.length > 0; }
  get canRedo() { return this.#redo.length > 0; }
}
