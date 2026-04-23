import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

type ToastKind = 'success' | 'info' | 'warning' | 'error';

export type ToastInput = {
  title: string;
  message?: string;
  kind?: ToastKind;
  durationMs?: number;
};

type ToastItem = ToastInput & {
  id: string;
  kind: ToastKind;
  durationMs: number;
};

type ToastApi = {
  toast: (t: ToastInput) => void;
  success: (title: string, message?: string) => void;
  error: (title: string, message?: string) => void;
};

const ToastContext = createContext<ToastApi | null>(null);

// Globaler Toast-Bus, damit auch "Mini-Roots" (z.B. einzelne Mounts)
// Toasts auslösen können, ohne direkt im gleichen React-Tree zu hängen.
type ToastListener = (t: ToastInput) => void;
const toastListeners = new Set<ToastListener>();
const pendingToasts: ToastInput[] = [];

function emitToast(t: ToastInput) {
  if (toastListeners.size === 0) {
    pendingToasts.push(t);
    if (pendingToasts.length > 10) pendingToasts.shift();
    return;
  }
  toastListeners.forEach((fn) => fn(t));
}

const busApi: ToastApi = {
  toast: (t) => emitToast(t),
  success: (title, message) => emitToast({ title, message, kind: 'success' }),
  error: (title, message) => emitToast({ title, message, kind: 'error', durationMs: 5000 }),
};

function kindClasses(kind: ToastKind) {
  switch (kind) {
    case 'success':
      return 'text-emerald-950';
    case 'warning':
      return 'text-amber-950';
    case 'error':
      return 'text-red-950';
    case 'info':
    default:
      return 'text-slate-900';
  }
}

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([]);
  const timers = useRef(new Map<string, number>());
  const [isDark, setIsDark] = useState(false);

  useEffect(() => {
    const read = () => document.documentElement.getAttribute('data-theme') === 'dark';
    setIsDark(read());
    const obs = new MutationObserver(() => setIsDark(read()));
    obs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    return () => obs.disconnect();
  }, []);

  const remove = useCallback((id: string) => {
    const t = timers.current.get(id);
    if (t) window.clearTimeout(t);
    timers.current.delete(id);
    setItems((prev) => prev.filter((x) => x.id !== id));
  }, []);

  const toast = useCallback(
    (t: ToastInput) => {
      const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
      const item: ToastItem = {
        id,
        title: t.title,
        message: t.message,
        kind: t.kind ?? 'info',
        durationMs: t.durationMs ?? 3500,
      };
      setItems((prev) => {
        const next = [item, ...prev].slice(0, 2);
        // Timer für rausgefallene Toasts entfernen
        prev.slice(2).forEach((old) => {
          const to = timers.current.get(old.id);
          if (to) window.clearTimeout(to);
          timers.current.delete(old.id);
        });
        return next;
      });
      const timeout = window.setTimeout(() => remove(id), item.durationMs);
      timers.current.set(id, timeout);
    },
    [remove],
  );

  useEffect(() => {
    const listener: ToastListener = (t) => toast(t);
    toastListeners.add(listener);
    if (pendingToasts.length) {
      const toFlush = pendingToasts.splice(0, pendingToasts.length);
      toFlush.forEach((t) => toast(t));
    }
    return () => {
      toastListeners.delete(listener);
    };
  }, [toast]);

  const api = useMemo<ToastApi>(
    () => ({
      toast,
      success: (title, message) => toast({ title, message, kind: 'success' }),
      error: (title, message) => toast({ title, message, kind: 'error', durationMs: 5000 }),
    }),
    [toast],
  );

  return (
    <ToastContext.Provider value={api}>
      {children}
      {typeof document !== 'undefined'
        ? createPortal(
            <div
              className="fixed !top-auto bottom-4 z-[9999] !pointer-events-none"
              style={{
                top: 'auto',
                bottom: '1rem',
                left: '50%',
                transform: 'translateX(-50%)',
                width: 'min(28rem, calc(100vw - 1.5rem))',
              }}
            >
              <div className="w-full px-0">
                <div className="relative">
                  {items[1] ? (
                    <div
                      className={['absolute inset-0 pointer-events-none rounded-3xl shadow-lg', kindClasses(items[1].kind)].join(' ')}
                      style={{
                        transform: 'translateY(10px) scale(0.97)',
                        opacity: 0.75,
                        backgroundColor: isDark ? '#0b1220' : '#ffffff',
                        color: isDark ? '#f8fafc' : '#0f172a',
                        mixBlendMode: 'normal',
                        filter: 'none',
                        borderRadius: '1.5rem',
                        overflow: 'hidden',
                      }}
                      aria-hidden="true"
                    />
                  ) : null}

                  {items[0] ? (
                    <div
                      className={['relative pointer-events-auto rounded-3xl shadow-lg', kindClasses(items[0].kind)].join(' ')}
                      style={{
                        opacity: 1,
                        backgroundColor: isDark ? '#0b1220' : '#ffffff',
                        color: isDark ? '#f8fafc' : '#0f172a',
                        mixBlendMode: 'normal',
                        filter: 'none',
                        borderRadius: '1.5rem',
                        overflow: 'hidden',
                      }}
                      role="status"
                      aria-live="polite"
                    >
                      <div className="flex items-center gap-3 px-6 py-5 text-sm">
                        <div className="min-w-0 flex-1 text-center">
                          <div className="text-sm font-semibold leading-5 break-words whitespace-normal">
                            {items[0].title}
                          </div>
                          {items[0].message ? (
                            <div className="mt-1 text-sm leading-5 break-words whitespace-normal">
                              {items[0].message}
                            </div>
                          ) : null}
                        </div>
                        <button
                          type="button"
                          onClick={() => remove(items[0].id)}
                          className={[
                            '!w-auto !p-1 !m-0 inline-flex items-center justify-center',
                            '!bg-transparent !text-current !shadow-none',
                            'rounded-full opacity-70 hover:opacity-100',
                            'focus-visible:outline-none',
                          ].join(' ')}
                          aria-label="Toast schließen"
                          title="Schließen"
                        >
                          <svg
                            className="h-4 w-4"
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
                      </div>
                    </div>
                  ) : null}
                </div>
              </div>
            </div>,
            document.body,
          )
        : null}
    </ToastContext.Provider>
  );
}

export function useToast(): ToastApi {
  const ctx = useContext(ToastContext);
  return ctx ?? busApi;
}

