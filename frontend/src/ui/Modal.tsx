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
    // Focus first focusable element or the dialog itself.
    const focusable = el.querySelector<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    );
    (focusable ?? el).focus();
  }, [open]);

  const labeledBy = useMemo(() => (title ? 'modal-title' : undefined), [title]);

  if (!open) return null;

  const node = (
    <div className="fixed inset-0 z-[20000]" role="presentation">
      <div
        className="absolute inset-0 bg-slate-950/60"
        onMouseDown={onClose}
        aria-hidden="true"
      />
      <div className="absolute inset-0 flex items-center justify-center p-3">
        <div
          ref={dialogRef}
          role="dialog"
          aria-modal="true"
          aria-labelledby={labeledBy}
          tabIndex={-1}
          onMouseDown={(e) => e.stopPropagation()}
          className={[
            'w-full',
            maxWidthClassName,
            'rounded-2xl border border-slate-200 bg-white shadow-xl',
            'dark:border-slate-800 dark:bg-slate-950',
            'outline-none',
          ].join(' ')}
        >
          {(title || showCloseButton) && (
            <div className="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
              {title ? (
                <div id="modal-title" className="text-sm font-semibold text-slate-900 dark:text-slate-50">
                  {title}
                </div>
              ) : (
                <span />
              )}
              {showCloseButton ? (
                <button
                  type="button"
                  onClick={onClose}
                  className="rounded-full p-1 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-slate-50"
                  aria-label="Schließen"
                  title="Schließen"
                >
                  <svg
                    className="h-5 w-5"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                  >
                    <path d="M18 6L6 18" />
                    <path d="M6 6l12 12" />
                  </svg>
                </button>
              ) : null}
            </div>
          )}
          <div
            className={['px-4 py-4 text-sm text-slate-700 dark:text-slate-200', bodyClassName]
              .filter(Boolean)
              .join(' ')}
          >
            {children}
          </div>
          {footer ? (
            <div className="flex items-center justify-end gap-2 border-t border-slate-200 px-4 py-3 dark:border-slate-800">
              {footer}
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );

  return typeof document !== 'undefined' ? createPortal(node, document.body) : node;
}

