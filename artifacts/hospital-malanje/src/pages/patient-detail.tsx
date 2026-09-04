import { ArrowLeft, CalendarDays, Edit3, FileText, MapPin, Phone, UserRound } from 'lucide-react';
import { Link, useParams } from 'wouter';
import { getGetPatientQueryKey, useGetPatient } from '@workspace/api-client-react';
import { Button, EmptyState, ErrorState, LoadingRows, PageHeading, StatusBadge } from '@/components/hospital-ui';

function dateLabel(value: string) {
  const parsed = value.includes('T') ? new Date(value) : new Date(`${value}T12:00:00`);
  if (Number.isNaN(parsed.getTime())) return 'Data indisponível';
  return new Intl.DateTimeFormat('pt-PT', { day: '2-digit', month: 'long', year: 'numeric' }).format(parsed);
}

export default function PatientDetail() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const query = useGetPatient(id, { query: { enabled: Number.isFinite(id), queryKey: getGetPatientQueryKey(id), staleTime: 20_000 } });
  const patient = query.data;
  if (query.isLoading) return <div><LoadingRows rows={4} /></div>;
  if (query.isError || !patient) return <div className="rounded-[14px] border border-border bg-card"><ErrorState message="Não foi possível abrir a ficha deste paciente." onRetry={() => query.refetch()} /></div>;
  return <div className="animate-enter">
    <Link href="/pacientes" data-testid="link-back-patients" className="mb-5 inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground transition hover:text-primary"><ArrowLeft size={15} /> Voltar aos pacientes</Link>
    <PageHeading eyebrow="Ficha do paciente" title={patient.name} description={`Processo ${patient.medicalRecordNumber}`}><Button variant="outline" testId="button-edit-patient"><Edit3 size={15} /> Editar ficha</Button></PageHeading>
    <section className="grid gap-4 lg:grid-cols-[0.85fr_1.6fr]">
      <div className="rounded-[14px] border border-border bg-card p-5 shadow-soft"><div className="flex items-center gap-3 border-b border-border pb-5"><div className="flex size-14 items-center justify-center rounded-[17px] bg-secondary text-lg font-bold text-primary">{patient.name.split(' ').map(part => part[0]).slice(0, 2).join('').toUpperCase()}</div><div><p className="text-base font-bold">{patient.name}</p><p className="mt-1 font-mono-ui text-[10px] text-muted-foreground">{patient.medicalRecordNumber}</p></div></div><div className="space-y-4 pt-5"><div className="flex items-start gap-3"><UserRound size={16} className="mt-0.5 text-primary" /><div><p className="text-[10px] uppercase tracking-wider text-muted-foreground">Dados pessoais</p><p className="mt-1 text-sm">{patient.sex === 'female' ? 'Feminino' : patient.sex === 'male' ? 'Masculino' : 'Outro'} · {dateLabel(patient.birthDate)}</p></div></div><div className="flex items-start gap-3"><Phone size={16} className="mt-0.5 text-primary" /><div><p className="text-[10px] uppercase tracking-wider text-muted-foreground">Telefone</p><p className="mt-1 text-sm">{patient.phone}</p></div></div><div className="flex items-start gap-3"><MapPin size={16} className="mt-0.5 text-primary" /><div><p className="text-[10px] uppercase tracking-wider text-muted-foreground">Residência</p><p className="mt-1 text-sm">{patient.neighborhood || 'Não indicado'}</p></div></div></div><div className="mt-6 rounded-lg bg-[#f9f5ed] p-3"><p className="text-[10px] uppercase tracking-wider text-muted-foreground">Registado em</p><p className="mt-1 font-mono-ui text-xs">{dateLabel(patient.createdAt.slice(0, 10))}</p></div></div>
      <div className="rounded-[14px] border border-border bg-card shadow-soft"><div className="flex items-center justify-between border-b border-border px-5 py-4"><div><h2 className="text-sm font-bold">Histórico de consultas</h2><p className="mt-0.5 text-xs text-muted-foreground">{patient.appointments.length} registos associados</p></div><FileText size={17} className="text-primary" /></div>{patient.appointments.length ? <div className="divide-y divide-border">{patient.appointments.map(appointment => <div key={appointment.id} data-testid={`patient-appointment-${appointment.id}`} className="grid gap-3 px-5 py-4 sm:grid-cols-[110px_minmax(0,1fr)_auto] sm:items-center"><div><p className="font-mono-ui text-xs font-bold text-primary">{appointment.time.slice(0, 5)}</p><p className="mt-1 text-[10px] text-muted-foreground">{dateLabel(appointment.date)}</p></div><div><p className="text-sm font-bold">{appointment.departmentName}</p><p className="mt-1 text-xs text-muted-foreground">{appointment.doctorName} · {appointment.type === 'first_visit' ? 'Primeira consulta' : appointment.type === 'follow_up' ? 'Seguimento' : 'Retorno'}</p>{appointment.notes && <p className="mt-2 text-xs italic text-muted-foreground">“{appointment.notes}”</p>}</div><StatusBadge status={appointment.status} /></div>)}</div> : <EmptyState title="Sem consultas registadas" description="O histórico será preenchido quando este paciente tiver uma consulta agendada." icon={CalendarDays} />}</div>
    </section>
  </div>;
}