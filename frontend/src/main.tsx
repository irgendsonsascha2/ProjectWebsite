import React from 'react';
import { createRoot } from 'react-dom/client';
import { ThemeToggle } from './components/ThemeToggle';

import './app.css';

import { ToastProvider } from './ui/toast';
import { Button } from './ui/Button';
import { Modal } from './ui/Modal';
import { Card } from './ui/Card';
import { useToast } from './ui/toast';
import { CopyButton } from './ui/CopyButton';
import { CopyField } from './ui/CopyField';
import { MediaLightbox } from './features/projectDetail/MediaLightbox';
import { InviteCodes } from './features/account/InviteCodes';

function Bootstrap() {
  const { success } = useToast();
  const [open, setOpen] = React.useState(false);

  // Debug: wenn React läuft, kann es einen Test-Toast anzeigen.
  React.useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('reactdebug') === '1') {
      window.setTimeout(() => {
        success('React aktiv', 'Wenn du das siehst, funktionieren Toasts grundsätzlich.');
      }, 250);
    }
  }, [success]);

  // React-Replace für vorhandene Copy-Buttons:
  // PHP liefert nur Mount-Container mit data-copy-text / aria-label.
  React.useEffect(() => {
    const fieldNodes = document.querySelectorAll<HTMLElement>('[data-react-copy-field][data-copy-value][data-copy-kind]');
    fieldNodes.forEach((node) => {
      if ((node as any).__reactRoot) return;
      const value = node.getAttribute('data-copy-value') || '';
      const kind = (node.getAttribute('data-copy-kind') || 'code') as 'code' | 'link';
      const label = node.getAttribute('data-copy-label') || 'Kopieren';
      const root = createRoot(node);
      (node as any).__reactRoot = root;
      root.render(
        <React.StrictMode>
          <CopyField kind={kind} value={value} copyLabel={label} />
        </React.StrictMode>,
      );
    });

    const nodes = document.querySelectorAll<HTMLElement>('[data-react-copy-button][data-copy-text]');
    nodes.forEach((node) => {
      if ((node as any).__reactRoot) return;
      const text = node.getAttribute('data-copy-text') || '';
      const label = node.getAttribute('aria-label') || node.getAttribute('title') || 'Kopieren';
      const root = createRoot(node);
      (node as any).__reactRoot = root;
      root.render(
        <React.StrictMode>
          <CopyButton text={text} label={label} />
        </React.StrictMode>,
      );
    });
  }, []);

  // Nur als dev/demo: wenn du das nicht willst, sag Bescheid – dann entferne ich das wieder.
  const showDemo = import.meta.env.DEV;
  return (
    <>
      <MediaLightbox />
      {/* Account-Seite: Einladungscodes React-native rendern */}
      <InviteCodes />
      {showDemo ? (
        <div className="fixed bottom-3 left-3 z-[9997]">
          <Card className="flex items-center gap-2 p-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => {
                success('Toast funktioniert', 'Das ist ein Tailwind/React-Toast.');
              }}
            >
              Toast testen
            </Button>
            <Button variant="ghost" size="sm" onClick={() => setOpen(true)}>
              Modal testen
            </Button>
          </Card>

          <Modal
            open={open}
            title="React Modal"
            onClose={() => setOpen(false)}
            footer={
              <>
                <Button variant="ghost" onClick={() => setOpen(false)}>
                  Schließen
                </Button>
                <Button
                  onClick={() => {
                    setOpen(false);
                    success('OK', 'Modal geschlossen.');
                  }}
                >
                  OK
                </Button>
              </>
            }
          >
            Das ist ein Beispiel-Modal. Als nächstes ersetzen wir echte UI-Teile damit.
          </Modal>
        </div>
      ) : null}
    </>
  );
}

function mountReact() {
  const bootstrapEl = document.getElementById('react-root');
  if (bootstrapEl && !(bootstrapEl as any).__reactRoot) {
    const root = createRoot(bootstrapEl);
    (bootstrapEl as any).__reactRoot = root;
    root.render(
      <React.StrictMode>
        <ToastProvider>
          <Bootstrap />
        </ToastProvider>
      </React.StrictMode>,
    );
  }

  const themeEl = document.getElementById('react-theme-toggle');
  if (themeEl && !(themeEl as any).__reactRoot) {
    const root = createRoot(themeEl);
    (themeEl as any).__reactRoot = root;
    root.render(
      <React.StrictMode>
        <ThemeToggle />
      </React.StrictMode>,
    );
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountReact, { once: true });
} else {
  mountReact();
}

