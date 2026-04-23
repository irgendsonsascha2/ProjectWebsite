import React from 'react';
import { Modal } from '../../ui/Modal';

type MediaType = 'image' | 'video';

type State = {
  open: boolean;
  type: MediaType;
  src: string;
};

export function MediaLightbox() {
  const [state, setState] = React.useState<State>({ open: false, type: 'image', src: '' });

  React.useEffect(() => {
    const isProjectDetail = !!document.querySelector('main.page-project_detail');
    if (!isProjectDetail) return;

    // Sicherheit: Legacy-Lightbox ist aktuell das verlässliche Verhalten.
    // React-Lightbox aktivieren nur, wenn explizit per Query-Param gewünscht.
    const params = new URLSearchParams(window.location.search);
    if (params.get('reactlb') !== '1') return;

    const onClickCapture = (e: MouseEvent) => {
      const target = e.target as HTMLElement | null;
      if (!target) return;
      const btn = target.closest?.('.media-item[data-src]') as HTMLElement | null;
      if (!btn) return;

      // Alte Lightbox-Handler verhindern (sie hängen direkt an den Buttons).
      e.preventDefault();
      e.stopPropagation();
      (e as any).stopImmediatePropagation?.();

      const type = (btn.getAttribute('data-type') || 'image') as MediaType;
      const src = btn.getAttribute('data-src') || '';
      if (!src) return;

      setState({ open: true, type, src });
    };

    document.addEventListener('click', onClickCapture, true);
    return () => document.removeEventListener('click', onClickCapture, true);
  }, []);

  return (
    <Modal
      open={state.open}
      onClose={() => setState((s) => ({ ...s, open: false }))}
      maxWidthClassName="max-w-6xl"
      bodyClassName="p-3 sm:p-4"
      showCloseButton
    >
      <div className="flex items-center justify-center">
        {state.type === 'video' ? (
          <video
            src={state.src}
            controls
            autoPlay
            playsInline
            className="max-h-[78vh] w-full rounded-xl bg-black object-contain"
          />
        ) : (
          <img
            src={state.src}
            alt=""
            className="max-h-[78vh] w-full rounded-xl bg-black object-contain"
          />
        )}
      </div>
    </Modal>
  );
}

