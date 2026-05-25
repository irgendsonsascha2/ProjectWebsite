import React, { useEffect, useMemo, useRef } from 'react';
import { createPortal } from 'react-dom';

export type ModalProps = {
  open: boolean;
  title?: string;
  onClose: () => void;
  children: React.ReactNode;
  footer?: React.ReactNode;
  maxWidthClassName?: string;
  bodyClassName?: string;
  showCloseButton?: boolean;
};

export function Modal({
  open,
  title,
  onClose,
  children,
  footer,
  maxWidthClassName = 'max-w-lg',
  bodyClassName,
  showCloseButton = true,
}: ModalProps) {
  const dialogRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    if (!open) return;
    const onKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [open, onClose]);

  useEffect(() => {
    if (!open) return;
    const el = dialogRef.current;
    if (!el) return;
    const input = el.querySelector<HTMLElement>(
      'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])',
    );
    if (input) {
      input.focus();
      return;
    }
    const buttons = el.querySelectorAll<HTMLButtonElement>('button:not([disabled])');
    for (const btn of buttons) {
      if (btn.classList.contains('site-modal-close')) continue;
      btn.focus();
      return;
    }
    el.focus();
  }, [open]);

  const labeledBy = useMemo(() => (title ? 'modal-title' : undefined), [title]);

  const onBackdropMouseDown = (e: React.MouseEvent<HTMLDivElement>) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  if (!open) return null;

  const node = (
    <div
      className="site-modal-backdrop"
      role="presentation"
      onMouseDown={onBackdropMouseDown}
    >
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={labeledBy}
        tabIndex={-1}
        onMouseDown={(e) => e.stopPropagation()}
        className={['site-modal', maxWidthClassName].filter(Boolean).join(' ')}
      >
        {showCloseButton ? (
          <button
            type="button"
            className="site-modal-close"
            onClick={onClose}
            aria-label="Schließen"
            title="Schließen"
          >
            ×
          </button>
        ) : null}
        {title ? (
          <div className="site-modal__header">
            <div id="modal-title" className="site-modal__title">
              {title}
            </div>
          </div>
        ) : null}
        <div className={['site-modal__body', bodyClassName].filter(Boolean).join(' ')}>
          {children}
        </div>
        {footer ? <div className="site-modal__footer">{footer}</div> : null}
      </div>
    </div>
  );

  return typeof document !== 'undefined' ? createPortal(node, document.body) : node;
}
