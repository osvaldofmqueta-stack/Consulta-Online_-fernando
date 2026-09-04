import { ArrowRight, MapPin, Plus, UserPlus, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'wouter';
import { useQueryClient } from '@tanstack/react-query';
import { PatientInputSex, getListPatientsQueryKey, useCreatePatient, useListPatients } from '@workspace/api-client-react';
import { Button, Drawer, EmptyState, ErrorState, FormField, LoadingRows, PageHeading, SearchField, SelectField, SpinnerButton } from '@/components/hospital-ui';

const inputClass = 'h-10 w-full rounded-[9px] border border-input bg-card px-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15';

function ageFromDate(date: string) {
  if (!date) return '—';
  const birth = new Date(date.includes('T') ? date : `${date}T12:00:00`);
  if (Number.isNaN(birth.getTime())) return '—';
  const now = new Date();
  let age = now.getFullYear() - birth.getFullYear();
  if (now < new Date(now.getFullYear(), birth.getMonth(), birth.getDate())) age--;
  return `${age} anos`;
}

function NewPatientDrawer({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [name, setName] = useState('');
  const [sex, setSex] = useState<string>(PatientInputSex.female);
  const [birthDate, setBirthDate] = useState('');
  const [phone, setPhone] = useState('');
  const [neighborhood, setNeighborhood] = useState('');
  const queryClient = useQueryClient();
  const create = useCreatePatient();
  const submit = () => {
    if (!name.trim() || !birthDate || !phone) return;
    create.mutate({ data: { name: name.trim(), sex: sex as typeof PatientInputSex[keyof typeof PatientInputSex], birthDate, phone, neighborhood: neighborhood || undefined } }, { onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['/api/patients'] }); onClose(); setName(''); setBirthDate(''); setPhone(''); setNeighborhood(''); } });
  };
  return <Drawer open={open} onClose={onClose} title="Registar paciente" subtitle="Um novo registo, com a atenção que cada pessoa merece."><div className="space-y-5">
    <FormField label="Nome completo"><input data-testid="input-patient-name" value={name} onChange={e => setName(e.target.value)} placeholder="Nome completo do paciente" className={inputClass} /></FormField>
    <div className="grid gap-4 sm:grid-cols-2"><FormField label="Sexo"><SelectField testId="select-patient-sex" value={sex} onChange={setSex}><option value={PatientInputSex.female}>Feminino</option><option value={PatientInputSex.male}>Masculino</option><option value={PatientInputSex.other}>Outro</option></SelectField></FormField><FormField label="Data de nascimento"><input data-testid="input-patient-birthdate" type="date" value={birthDate} onChange={e => setBirthDate(e.target.value)} className={inputClass} /></FormField></div>
    <FormField label="Telefone"><input data-testid="input-patient-phone" value={phone} onChange={e => setPhone(e.target.value)} placeholder="+244 9XX XXX XXX" className={inputClass} /></FormField>
    <FormField label="Bairro" hint="Opcional · ajuda a orientar a área de residência"><input data-testid="input-patient-neighborhood" value={neighborhood} onChange={e => setNeighborhood(e.target.value)} placeholder="Ex.: Maxinde" className={inputClass} /></FormField>
    {create.isError && <p className="rounded-lg bg-[#fae6df] p-3 text-xs text-destructive">Não foi possível registar este paciente. Confirme os dados e tente novamente.</p>}<Button testId="button-submit-patient" onClick={submit} disabled={create.isPending || !name.trim() || !birthDate || !phone} className="w-full"><SpinnerButton loading={create.isPending}>Registar paciente</SpinnerButton></Button>
  </div></Drawer>;
}

export default function Patients() {
  const [search, setSearch] = useState('');
  const [drawer, setDrawer] = useState(false);
  const query = useListPatients({ search: search || undefined, limit: 100 }, { query: { queryKey: getListPatientsQueryKey({ search: search || undefined, limit: 100 }), staleTime: 20_000 } });
  const patients = query.data ?? [];
  return <div className="animate-enter">
    <PageHeading eyebrow="Registo · Pessoas" title="Pacientes" description="Encontre rapidamente um processo ou abra um novo registo."><Button testId="button-open-patient-drawer" onClick={() => setDrawer(true)}><UserPlus size={16} /> Registar paciente</Button></PageHeading>
    <div className="mb-5 flex flex-col gap-3 sm:flex-row"><SearchField value={search} onChange={setSearch} placeholder="Nome ou número de processo" testId="input-search-patients" /><div className="flex items-center gap-2 rounded-lg border border-border bg-card px-3 text-xs text-muted-foreground"><UsersRound size={15} className="text-primary" />{patients.length} registos</div></div>
    <div className="rounded-[14px] border border-border bg-card shadow-soft">
      <div className="hidden grid-cols-[minmax(240px,1.5fr)_130px_100px_minmax(130px,1fr)_auto] gap-4 border-b border-border px-5 py-3 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground md:grid"><span>Paciente</span><span>Processo</span><span>Idade</span><span>Contacto</span><span /></div>
      {query.isLoading ? <LoadingRows /> : query.isError ? <ErrorState onRetry={() => query.refetch()} /> : patients.length ? <div className="divide-y divide-border">{patients.map(patient => <Link href={`/pacientes/${patient.id}`} data-testid={`row-patient-${patient.id}`} key={patient.id} className="grid grid-cols-1 gap-2 px-5 py-4 transition hover:bg-[#fbf8f2] md:grid-cols-[minmax(240px,1.5fr)_130px_100px_minmax(130px,1fr)_auto] md:items-center md:gap-4"><div className="flex items-center gap-3"><div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-bold text-primary">{patient.name.split(' ').map(part => part[0]).slice(0, 2).join('').toUpperCase()}</div><div><p className="text-sm font-bold text-foreground">{patient.name}</p><p className="mt-0.5 flex items-center gap-1 text-[11px] text-muted-foreground"><MapPin size={11} />{patient.neighborhood || 'Bairro não indicado'}</p></div></div><span className="font-mono-ui text-[11px] text-muted-foreground md:text-xs">{patient.medicalRecordNumber}</span><span className="text-xs text-muted-foreground">{ageFromDate(patient.birthDate)}</span><span className="text-xs text-muted-foreground">{patient.phone}</span><span className="flex items-center gap-1 text-xs font-semibold text-primary">Ver ficha <ArrowRight size={14} /></span></Link>)}</div> : <EmptyState title="Ainda sem pacientes" description={search ? 'Nenhum registo corresponde à sua pesquisa.' : 'Comece por registar a primeira pessoa atendida hoje.'} icon={UsersRound} action={<Button testId="button-empty-new-patient" onClick={() => setDrawer(true)}><Plus size={15} /> Registar paciente</Button>} />}
    </div><NewPatientDrawer open={drawer} onClose={() => setDrawer(false)} />
  </div>;
}