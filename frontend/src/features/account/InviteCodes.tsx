import React from 'react';
import { CopyField } from '../../ui/CopyField';

type InviteCodeItem = {
  role: string;
  code: string;
  link: string;
};

function readInviteCodesFromDom(): InviteCodeItem[] {
  const el = document.getElementById('react-invite-codes-data');
  if (!el) return [];
  try {
    const raw = el.textContent || '[]';
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed.filter(Boolean) as InviteCodeItem[];
  } catch {
    return [];
  }
}

export function InviteCodes() {
  const [items] = React.useState(() => readInviteCodesFromDom());

  if (!items.length) return null;

  // Fallback-Tabelle ausblenden, sobald React rendert.
  React.useEffect(() => {
    const tableWrap = document.querySelector('.admin-panel .table-wrap') as HTMLElement | null;
    if (tableWrap) tableWrap.style.display = 'none';
  }, []);

  return (
    <div className="mt-3 space-y-3">
      <div className="mx-auto w-full max-w-[52rem] text-sm font-semibold text-[color:var(--text-main)]">
        Aktive Einladungscodes
      </div>

      <div className="mx-auto grid w-full max-w-[52rem] gap-3">
        {items.map((it, idx) => (
          <div
            key={`${it.code}-${idx}`}
            className="rounded-2xl border border-[color:var(--border-subtle)] bg-[color:var(--card-bg)] p-3 shadow-sm"
          >
            <div className="flex items-center justify-between gap-2">
              <div className="text-xs font-semibold uppercase tracking-wide text-[color:var(--secondary-color)]">
                Rolle
              </div>
              <div className="rounded-full bg-[color:var(--surface-muted)] px-3 py-1 text-xs font-semibold text-[color:var(--text-main)]">
                {it.role}
              </div>
            </div>

            <div className="mt-3 grid gap-3 md:grid-cols-2 md:items-start">
              <div className="min-w-0">
                <div className="mb-1 text-xs font-semibold text-[color:var(--secondary-color)]">Code</div>
                <CopyField kind="code" value={it.code} copyLabel="Code kopieren" />
              </div>
              <div className="min-w-0">
                <div className="mb-1 text-xs font-semibold text-[color:var(--secondary-color)]">Direkt-Link</div>
                <CopyField kind="link" value={it.link} copyLabel="Link kopieren" />
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

