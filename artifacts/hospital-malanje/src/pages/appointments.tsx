import { CalendarDays, Check, CircleDot, Filter, Plus, UserRound, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, useLocation } from 'wouter';
import { useQueryClient } from '@tanstack/react-query';
import { AppointmentStatus, AppointmentInputType, getListAppointmentsQueryKey, getListDepartmentsQueryKey, getListDoctorsQueryKey, getListPatientsQueryKey, useCreateAppointment, useListAppointments, useListDepartments, useListDoctors, useListPatients, useUpdateAppointment } from '@workspace/api-client-react';
import type { Appointment } from '@workspace/api-client-react';
import { Button, Drawer, EmptyState, ErrorState, FormField, LoadingRows, PageHeading, SearchField, SelectField, SpinnerButton, StatusBadge } from '@/components/hospital-ui';

const today = new Date().toISOString().slice(0, 10);
const inputClass = 'h-10 w-full rounded-[9px] border border-input bg-card px-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15';

function AppointmentRow({ appointment, onStatus, busy }: { appointment: Appointment; onStatus: (id: number, status: typeof AppointmentStatus[keyof typeof AppointmentStatus]) => void; busy: boolean }) {
  const nextAction = appointment.status === 'waiting' ? { label: 'Iniciar', status: AppointmentStatus.in_progress } : appointment.status === 'in_progress' ? { label: 'Concluir', status: AppointmentStatus.completed } : appointment.status === 'scheduled' ? { label: 'Chamar', status: AppointmentStatus.waiting } : appointment.status === 'confirmed' ? { label: 'Chamar', status: AppointmentStatus.waiting } : null;
  return <div data-testid={`row-appointment-${appointment.id}`} className="group grid grid-cols-[76px_minmax(170px,1.4fr)_minmax(130px,1fr)_105px_120px_auto] items-center gap-3 border-b border-border px-4 py-3.5 transition hover:bg-[#fbf8f2]">
    <div className="font-mono-ui text-[13px] font-bold text-primary">{appointment.time.slice(0, 5)}</div>
    <Link href={`/pacientes/${appointment.patientId}`} data-testid={`link-patient-${appointment.patientId}`} className="min-w-0"><p className="truncate text-sm font-bold group-hover:text-primary">{appointment.patientName}</p><p className="mt-0.5 flex items-center gap-1 text-[10px] text-muted-foreground"><UserRound size={11} />{appointment.medicalRecordNumber}</p></Link>
    <div className="min-w-0"><p className="truncate text-xs font-semibold">{appointment.departmentName}</p><p className="mt-0.5 truncate text-[11px] text-muted-foreground">{appointment.doctorName}</p></div>
    <div><span className="text-[11px] text-muted-foreground">{appointment.type === 'first_visit' ? '1ª consulta' : appointment.type === 'follow_up' ? 'Seguimento' : 'Retorno'}</span></div>
    <StatusBadge status={appointment.status} />
    <div className="flex justify-end gap-1.5">{nextAction && <button data-testid={`button-status-${appointment.id}`} disabled={busy} onClick={() => onStatus(appointment.id, nextAction.status)} className="hidden h-8 items-center gap-1 rounded-lg bg-secondary px-2.5 text-[11px] font-semibold text-primary transition hover:brightness-95 xl:flex">{busy ? <CircleDot size={13} className="animate-pulse" /> : <Check size={13} />}{nextAction.label}</button>}<button data-testid={`button-cancel-${appointment.id}`} disabled={busy || ['completed', 'cancelled', 'no_show'].includes(appointment.status)} onClick={() => onStatus(appointment.id, AppointmentStatus.cancelled)} aria-label={`Cancelar consulta de ${appointment.patientName}`} className="rounded-lg p-2 text-muted-foreground transition hover:bg-[#fae6df] hover:text-destructive disabled:opacity-30"><X size={15} /></button></div>
  </div>;
}

function NewAppointmentDrawer({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [patientId, setPatientId] = useState('');
  const [departmentId, setDepartmentId] = useState('');
  const [doctorId, setDoctorId] = useState('');
  const [date, setDate] = useState(today);
  const [time, setTime] = useState('08:00');
  const [type, setType] = useState<string>(AppointmentInputType.first_visit);
  const [notes, setNotes] = useState('');
  const queryClient = useQueryClient();
  const patientsQuery = useListPatients({ limit: 100 }, { query: { queryKey: getListPatientsQueryKey({ limit: 100 }), staleTime: 60_000 } });
  const departmentsQuery = useListDepartments({ query: { queryKey: getListDepartmentsQueryKey(), staleTime: 60_000 } });
  const doctorParams = departmentId ? { departmentId: Number(departmentId) } : undefined;
  const doctorsQuery = useListDoctors(doctorParams, { query: { queryKey: getListDoctorsQueryKey(doctorParams), staleTime: 60_000 } });
  const create = useCreateAppointment();
  const doctors = doctorsQuery.data ?? [];
  const submit = () => {
    if (!patientId || !departmentId || !doctorId || !date || !time) return;
    create.mutate({ data: { patientId: Number(patientId), departmentId: Number(departmentId), doctorId: Number(doctorId), date, time, type: type as typeof AppointmentInputType[keyof typeof AppointmentInputType], notes: notes || undefined } }, { onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['/api/appointments'] }); onClose(); setPatientId(''); setDepartmentId(''); setDoctorId(''); setDate(today); setTime('08:00'); setType(AppointmentInputType.first_visit); setNotes(''); } });
  };
  return <Drawer open={open} onClose={onClose} title="Nova consulta" subtitle="Registe o próximo encontro com cuidado."><div className="space-y-5">
    <FormField label="Paciente"><SelectField testId="select-appointment-patient" value={patientId} onChange={setPatientId}><option value="">Seleccionar paciente</option>{(patientsQuery.data ?? []).map(patient => <option key={patient.id} value={patient.id}>{patient.name} · {patient.medicalRecordNumber}</option>)}</SelectField></FormField>
    <div className="grid gap-4 sm:grid-cols-2"><FormField label="Departamento"><SelectField testId="select-appointment-department" value={departmentId} onChange={value => { setDepartmentId(value); setDoctorId(''); }}><option value="">Seleccionar</option>{(departmentsQuery.data ?? []).filter(dept => dept.active).map(dept => <option key={dept.id} value={dept.id}>{dept.name}</option>)}</SelectField></FormField><FormField label="Profissional"><SelectField testId="select-appointment-doctor" value={doctorId} onChange={setDoctorId}><option value="">Seleccionar</option>{doctors.filter(doctor => doctor.active).map(doctor => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</SelectField></FormField></div>
    <div className="grid gap-4 sm:grid-cols-2"><FormField label="Data"><input data-testid="input-appointment-date" type="date" value={date} onChange={e => setDate(e.target.value)} className={inputClass} /></FormField><FormField label="Hora"><input data-testid="input-appointment-time" type="time" value={time} onChange={e => setTime(e.target.value)} className={inputClass} /></FormField></div>
    <FormField label="Tipo de consulta"><SelectField testId="select-appointment-type" value={type} onChange={setType}><option value={AppointmentInputType.first_visit}>Primeira consulta</option><option value={AppointmentInputType.follow_up}>Seguimento</option><option value={AppointmentInputType.return}>Retorno</option></SelectField></FormField>
    <FormField label="Notas" hint="Opcional · informação útil para a equipa"><textarea data-testid="input-appointment-notes" value={notes} onChange={e => setNotes(e.target.value)} rows={3} placeholder="Ex.: encaminhamento, prioridade..." className="w-full resize-none rounded-[9px] border border-input bg-card p-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15" /></FormField>
    {create.isError && <p className="rounded-lg bg-[#fae6df] p-3 text-xs text-destructive">Não foi possível criar a consulta. Confirme os dados e tente novamente.</p>}<Button testId="button-submit-appointment" onClick={submit} disabled={create.isPending || !patientId || !departmentId || !doctorId} className="w-full"><SpinnerButton loading={create.isPending}>Agendar consulta</SpinnerButton></Button>
  </div></Drawer>;
}

export default function Appointments() {
  const [location] = useLocation();
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [department, setDepartment] = useState('');
  const [date, setDate] = useState(today);
  const [drawer, setDrawer] = useState(() => location.includes('novo=1'));
  const params = useMemo(() => ({ date: date || undefined, status: status ? status as typeof AppointmentStatus[keyof typeof AppointmentStatus] : undefined, departmentId: department ? Number(department) : undefined, search: search || undefined }), [date, status, department, search]);
  const query = useListAppointments(params, { query: { queryKey: getListAppointmentsQueryKey(params), staleTime: 15_000, refetchInterval: 30_000 } });
  const departmentsQuery = useListDepartments({ query: { queryKey: getListDepartmentsQueryKey(), staleTime: 60_000 } });
  const update = useUpdateAppointment();
  const appointments = query.data ?? [];
  const statusChange = (id: number, next: typeof AppointmentStatus[keyof typeof AppointmentStatus]) => update.mutate({ id, data: { status: next } }, { onSuccess: () => query.refetch() });
  return <div className="animate-enter">
    <PageHeading eyebrow="Operação · Agenda" title="Consultas" description="Acompanhe a fila, chame o próximo paciente e mantenha cada passagem visível."><Button testId="button-open-appointment-drawer" onClick={() => setDrawer(true)}><Plus size={16} /> Nova consulta</Button></PageHeading>
    <div className="rounded-[14px] border border-border bg-card shadow-soft">
      <div className="flex flex-col gap-3 border-b border-border p-4 lg:flex-row"><SearchField value={search} onChange={setSearch} placeholder="Pesquisar paciente ou número de processo" testId="input-search-appointments" /><div className="grid grid-cols-2 gap-2 sm:flex"><SelectField testId="select-appointment-date" value={date} onChange={setDate} className="min-w-[144px]"><option value="">Todas as datas</option><option value={today}>Hoje · {new Intl.DateTimeFormat('pt-PT', { day: '2-digit', month: '2-digit' }).format(new Date())}</option></SelectField><SelectField testId="select-appointment-status" value={status} onChange={setStatus} className="min-w-[150px]"><option value="">Todos os estados</option>{Object.entries({ scheduled: 'Agendada', confirmed: 'Confirmada', waiting: 'Em espera', in_progress: 'Em atendimento', completed: 'Concluída', cancelled: 'Cancelada', no_show: 'Não compareceu' }).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</SelectField><SelectField testId="select-appointment-department-filter" value={department} onChange={setDepartment} className="col-span-2 min-w-[160px] sm:col-span-1"><option value="">Departamentos</option>{(departmentsQuery.data ?? []).map(dept => <option key={dept.id} value={dept.id}>{dept.name}</option>)}</SelectField></div></div>
      <div className="flex items-center justify-between bg-[#f9f5ed] px-4 py-2.5"><div className="flex items-center gap-2 text-xs text-muted-foreground"><Filter size={14} className="text-primary" /><span>{appointments.length} {appointments.length === 1 ? 'consulta encontrada' : 'consultas encontradas'}</span></div><span className="font-mono-ui text-[10px] uppercase tracking-wider text-muted-foreground">{date ? new Intl.DateTimeFormat('pt-PT', { weekday: 'short', day: 'numeric', month: 'short' }).format(new Date(`${date}T12:00:00`)) : 'Agenda completa'}</span></div>
      <div className="hidden min-w-[800px] grid-cols-[76px_minmax(170px,1.4fr)_minmax(130px,1fr)_105px_120px_auto] gap-3 border-b border-border px-4 py-2.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground md:grid"><span>Hora</span><span>Paciente</span><span>Departamento</span><span>Tipo</span><span>Estado</span><span /></div>
      {query.isLoading ? <LoadingRows /> : query.isError ? <ErrorState onRetry={() => query.refetch()} /> : appointments.length ? <div className="scrollbar-thin overflow-x-auto">{appointments.map(item => <AppointmentRow key={item.id} appointment={item} onStatus={statusChange} busy={update.isPending} />)}</div> : <EmptyState title="A agenda está tranquila" description="Não encontrámos consultas para estes filtros. Tente outra data ou registe uma nova consulta." icon={CalendarDays} action={<Button testId="button-empty-new-appointment" onClick={() => setDrawer(true)}><Plus size={15} /> Nova consulta</Button>} />}
    </div><NewAppointmentDrawer open={drawer} onClose={() => setDrawer(false)} />
  </div>;
}