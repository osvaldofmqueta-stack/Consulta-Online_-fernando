import { AlertCircle, Check, ChevronDown, LoaderCircle, Search, X } from 'lucide-react';
import { type ReactNode, useEffect, useRef, useState } from 'react';
import { AppointmentStatus } from '@workspace/api-client-react';

export const statusLabels: Record<string, string> = {
  scheduled: 'Agendada',
  confirmed: 'Confirmada',
  waiting: 'Em espera',
  in_progress: 'Em atendimento',
  completed: 'Concluída',
  cancelled: 'Cancelada',
  no_show: 'Não compareceu',
};

const statusClasses: Record<string, string> = {
  scheduled: 'bg-[#e9f0ed] text-[#315b52]',
  confirmed: 'bg-[#e4edf2] text-[#31566b]',
  waiting: 'bg-[#fff0dd] text-[#985321]',
  in_progress: 'bg-[#dff2ed] text-[#146b5d]',
  completed: 'bg-[#e4e9e5] text-[#4b645c]',
  cancelled: 'bg-[#f9e4e0] text-[#a54439]',
  no_show: 'bg-[#eee6df] text-[#765846]',
};

export function StatusBadge({ status }: { status: string }) {
  return <span data-testid={`status-${status}`} className={`inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold tracking-wide ${statusClasses[status] ?? 'bg-muted text-muted-foreground'}`}>{statusLabels[status] ?? status}</span>;
}

export function MetricCard({ label, value, detail, icon: Icon, tone = 'teal', className = '' }: { label: string; value: string | number; detail?: string; icon: typeof Search; tone?: 'teal' | 'coral' | 'amber' | 'blue'; className?: string }) {
  const tones = {
    teal: 'bg-[#e0efea] text-[#22685c]',
    coral: 'bg-[#fae6df] text-[#a6503c]',
    amber: 'bg-[#fff0d8] text-[#a3691f]',
    blue: 'bg-[#e2edf2] text-[#3b6576]',
  };
  return <div className={`rounded-[14px] border border-border bg-card p-4 shadow-[0_4px_16px_hsl(183_30%_22%_/_0.04)] ${className}`}>
    <div className="flex items-start justify-between gap-3">
      <div><p className="text-xs font-medium text-muted-foreground">{label}</p><p data-testid={`metric-${label}`} className="mt-2 font-mono-ui text-[27px] font-bold tracking-[-0.04em] text-foreground">{value}</p>{detail && <p className="mt-1 text-xs text-muted-foreground">{detail}</p>}</div>
      <div className={`flex size-9 items-center justify-center rounded-[10px] ${tones[tone]}`}><Icon size={17} strokeWidth={2.2} /></div>
    </div>
  </div>;
}

export function PageHeading({ eyebrow, title, description, children }: { eyebrow: string; title: string; description?: string; children?: ReactNode }) {
  return <div className="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div><p className="font-mono-ui text-[10px] uppercase tracking-[0.18em] text-primary">{eyebrow}</p><h1 className="mt-1 text-[26px] font-bold tracking-[-0.035em] text-foreground sm:text-[30px]">{title}</h1>{description && <p className="mt-1 max-w-2xl text-sm text-muted-foreground">{description}</p>}</div>
    {children && <div className="flex shrink-0 items-center gap-2">{children}</div>}
  </div>;
}

export function SearchField({ value, onChange, placeholder, testId = 'input-search' }: { value: string; onChange: (value: string) => void; placeholder: string; testId?: string }) {
  return <label className="relative block flex-1"><Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" /><input data-testid={testId} value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder} className="h-10 w-full rounded-[9px] border border-input bg-card pl-9 pr-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15" /></label>;
}

export function SelectField({ value, onChange, children, testId, className = '' }: { value: string; onChange: (value: string) => void; children: ReactNode; testId: string; className?: string }) {
  return <div className={`relative ${className}`}><select data-testid={testId} value={value} onChange={e => onChange(e.target.value)} className="h-10 w-full appearance-none rounded-[9px] border border-input bg-card px-3 pr-8 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15">{children}</select><ChevronDown size={15} className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground" /></div>;
}

export function LoadingRows({ rows = 5 }: { rows?: number }) {
  return <div className="space-y-2 p-4">{Array.from({ length: rows }).map((_, i) => <div key={i} className="h-14 animate-pulse-soft rounded-lg bg-muted" />)}</div>;
}

export function ErrorState({ message = 'Não foi possível carregar os dados.', onRetry }: { message?: string; onRetry?: () => void }) {
  return <div className="flex min-h-[230px] flex-col items-center justify-center gap-3 p-8 text-center"><div className="flex size-11 items-center justify-center rounded-full bg-[#fae6df] text-destructive"><AlertCircle size={21} /></div><p className="max-w-sm text-sm text-muted-foreground">{message}</p>{onRetry && <button data-testid="button-retry" onClick={onRetry} className="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-foreground transition hover:bg-muted">Tentar novamente</button>}</div>;
}

export function EmptyState({ title, description, icon: Icon = Search, action }: { title: string; description: string; icon?: typeof Search; action?: ReactNode }) {
  return <div className="flex min-h-[240px] flex-col items-center justify-center px-6 py-10 text-center"><div className="mb-4 flex size-12 items-center justify-center rounded-[14px] bg-secondary text-primary"><Icon size={21} /></div><h3 className="text-sm font-bold text-foreground">{title}</h3><p className="mt-1 max-w-xs text-xs leading-5 text-muted-foreground">{description}</p>{action && <div className="mt-4">{action}</div>}</div>;
}

export function Drawer({ open, onClose, title, subtitle, children, width = '480px' }: { open: boolean; onClose: () => void; title: string; subtitle?: string; children: ReactNode; width?: string }) {
  const panelRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!open) return;
    const onKey = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, onClose]);
  if (!open) return null;
  return <div className="fixed inset-0 z-50 flex justify-end"><button data-testid="button-close-overlay" aria-label="Fechar painel" onClick={onClose} className="absolute inset-0 cursor-default bg-[#123e3a]/35 backdrop-blur-[2px]" /><div ref={panelRef} style={{ width }} className="animate-enter relative flex h-full max-w-full flex-col border-l border-border bg-card shadow-2xl"><div className="flex items-start justify-between border-b border-border px-5 py-5"><div><h2 className="text-lg font-bold tracking-[-0.025em]">{title}</h2>{subtitle && <p className="mt-1 text-xs text-muted-foreground">{subtitle}</p>}</div><button data-testid="button-close-drawer" onClick={onClose} aria-label="Fechar" className="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"><X size={18} /></button></div><div className="scrollbar-thin flex-1 overflow-y-auto px-5 py-5">{children}</div></div></div>;
}

export function Button({ children, onClick, variant = 'primary', type = 'button', disabled = false, testId, className = '' }: { children: ReactNode; onClick?: () => void; variant?: 'primary' | 'outline' | 'quiet' | 'danger'; type?: 'button' | 'submit'; disabled?: boolean; testId?: string; className?: string }) {
  const styles = { primary: 'bg-primary text-primary-foreground hover:brightness-95', outline: 'border border-border bg-card text-foreground hover:bg-muted', quiet: 'text-muted-foreground hover:bg-muted hover:text-foreground', danger: 'bg-[#fae6df] text-destructive hover:bg-[#f5d7d0]' };
  return <button data-testid={testId} type={type} onClick={onClick} disabled={disabled} className={`inline-flex h-10 items-center justify-center gap-2 rounded-[9px] px-3.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 ${styles[variant]} ${className}`}>{children}</button>;
}

export function FormField({ label, children, hint }: { label: string; children: ReactNode; hint?: string }) {
  return <label className="block"><span className="mb-1.5 block text-xs font-semibold text-foreground">{label}</span>{children}{hint && <span className="mt-1 block text-[11px] text-muted-foreground">{hint}</span>}</label>;
}

export function SpinnerButton({ loading, children }: { loading: boolean; children: ReactNode }) {
  return <>{loading && <LoaderCircle size={15} className="animate-spin" />}{children}</>;
}

export const successIcon = <Check size={15} />;
export { AppointmentStatus };