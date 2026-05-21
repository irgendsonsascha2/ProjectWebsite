import React from 'react';
import { Modal } from './Modal';

export type HelpButtonProps = {
  title: string;
  sourceId: string;
};

export function HelpButton({ title, sourceId }: HelpButtonProps) {
  const [open, setOpen] = React.useState(false);
  const [content, setContent] = React.useState<React.ReactNode>(null);

  const onOpen = React.useCallback(() => {
    const source = document.getElementById(sourceId);
    if (source) {
      setContent(<div dangerouslySetInnerHTML={{ __html: source.innerHTML }} />);
    } else {
      setContent(<p>Hilfetext konnte nicht geladen werden.</p>);
    }
    setOpen(true);
  }, [sourceId]);

  return (
    <>
      <button
        type="button"
        className="btn-help-trigger"
        aria-label={title}
        title={title}
        onClick={onOpen}
      >
        ?
      </button>
      <Modal open={open} title={title} onClose={() => setOpen(false)} maxWidthClassName="max-w-md">
        {content}
      </Modal>
    </>
  );
}
