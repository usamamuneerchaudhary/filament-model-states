document.addEventListener('alpine:init', () => {
    Alpine.data('kanbanBoard', (config = {}) => ({
        transitions: config.transitions ?? {},
        labels: config.labels ?? {},
        confirmBeforeMove: config.confirmBeforeMove ?? true,
        sortables: [],
        showConfirm: false,
        pendingMove: null,
        awaitingMove: null,

        init() {
            this.$nextTick(() => this.mountSortables());

            this.$el.addEventListener('kanban-reinit', () => {
                this.resetModal();
                this.$nextTick(() => this.mountSortables());
            });

            if (typeof Livewire !== 'undefined') {
                Livewire.hook('morph.updated', ({ el }) => {
                    if (! this.$el.contains(el) && ! el.contains(this.$el)) {
                        return;
                    }

                    this.resetModal();
                    this.$nextTick(() => this.mountSortables());
                });
            }
        },

        resetModal() {
            this.showConfirm = false;
            this.pendingMove = null;
            this.awaitingMove = null;
        },

        mountSortables() {
            this.destroySortables();

            if (typeof Sortable === 'undefined') {
                return;
            }

            this.$el.querySelectorAll('.fi-model-states-column').forEach((columnEl) => {
                const sortable = Sortable.create(columnEl, {
                    group: {
                        name: 'kanban-columns',
                        put: (to, from) => this.canDrop(
                            from.el.dataset.column,
                            to.el.dataset.column,
                        ),
                    },
                    animation: 150,
                    draggable: '.fi-model-states-card',
                    ghostClass: 'fi-model-states-kanban__card--ghost',
                    dragClass: 'fi-model-states-kanban__card--dragging',
                    onStart: (evt) => {
                        this.highlightValidTargets(evt.from.dataset.column);
                    },
                    onEnd: (evt) => {
                        this.clearHighlights();

                        const recordId = evt.item.dataset.recordId;
                        const targetColumn = evt.to.dataset.column;
                        const sourceColumn = evt.from.dataset.column;

                        if (! recordId || ! targetColumn || ! sourceColumn || targetColumn === sourceColumn) {
                            return;
                        }

                        if (this.confirmBeforeMove) {
                            evt.from.appendChild(evt.item);

                            this.pendingMove = {
                                recordId,
                                sourceColumn,
                                targetColumn,
                                sourceLabel: this.labels[sourceColumn] ?? sourceColumn,
                                targetLabel: this.labels[targetColumn] ?? targetColumn,
                                cardTitle: evt.item.querySelector('.fi-model-states-kanban__card-title')?.textContent?.trim() ?? 'this record',
                                item: evt.item,
                                fromEl: evt.from,
                                toEl: evt.to,
                            };

                            this.showConfirm = true;

                            return;
                        }

                        this.submitMove(recordId, sourceColumn, targetColumn, evt.item, evt.to);
                    },
                });

                this.sortables.push(sortable);
            });
        },

        destroySortables() {
            this.sortables.forEach((sortable) => sortable.destroy());
            this.sortables = [];
        },

        getWire() {
            return this.$wire ?? null;
        },

        canDrop(source, target) {
            if (! source || ! target || source === target) {
                return false;
            }

            const allowed = this.transitions[source] ?? [];

            return allowed.includes(target);
        },

        highlightValidTargets(source) {
            const allowed = this.transitions[source] ?? [];

            this.$el.querySelectorAll('.fi-model-states-kanban__column').forEach((column) => {
                const key = column.dataset.column;

                if (key === source) {
                    column.classList.add('fi-model-states-kanban__column--source');

                    return;
                }

                if (allowed.includes(key)) {
                    column.classList.add('fi-model-states-kanban__column--droppable');
                } else {
                    column.classList.add('fi-model-states-kanban__column--blocked');
                }
            });
        },

        clearHighlights() {
            this.$el.querySelectorAll('.fi-model-states-kanban__column').forEach((column) => {
                column.classList.remove(
                    'fi-model-states-kanban__column--droppable',
                    'fi-model-states-kanban__column--blocked',
                    'fi-model-states-kanban__column--source',
                );
            });
        },

        confirmMove() {
            if (! this.pendingMove) {
                return;
            }

            this.awaitingMove = { ...this.pendingMove };
            this.showConfirm = false;
            this.pendingMove = null;

            const wire = this.getWire();

            if (wire) {
                wire.moveRecord(this.awaitingMove.recordId, this.awaitingMove.targetColumn);
            }
        },

        cancelMove() {
            this.showConfirm = false;
            this.pendingMove = null;
        },

        submitMove(recordId, sourceColumn, targetColumn, item, toEl) {
            this.awaitingMove = {
                recordId,
                sourceColumn,
                targetColumn,
                item,
                toEl,
            };

            const wire = this.getWire();

            if (wire) {
                wire.moveRecord(recordId, targetColumn);
            }
        },

        handleMoveAccepted(detail) {
            const payload = Array.isArray(detail) ? detail[0] : detail;

            if (! this.awaitingMove || ! payload) {
                return;
            }

            if (this.awaitingMove.item && this.awaitingMove.toEl) {
                this.awaitingMove.toEl.appendChild(this.awaitingMove.item);
            }

            this.updateCounts(payload.sourceColumn, payload.targetColumn);
            this.awaitingMove = null;
        },

        handleMoveRejected() {
            this.resetModal();
            this.getWire()?.$refresh();
        },

        updateCounts(source, target) {
            const adjust = (column, delta) => {
                const badge = this.$el.querySelector(`[data-count-column="${column}"]`);

                if (! badge) {
                    return;
                }

                const text = badge.textContent ?? '';
                const match = text.match(/^(\d+)/);
                const current = match ? Number.parseInt(match[1], 10) : 0;
                const totalPart = text.includes('/') ? text.slice(text.indexOf('/')) : '';

                badge.textContent = `${Math.max(0, current + delta)}${totalPart}`;
            };

            adjust(source, -1);
            adjust(target, 1);
        },
    }));
});
