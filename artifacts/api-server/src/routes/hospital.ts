import { Router, type IRouter } from "express";
import { and, asc, desc, eq, ilike, or } from "drizzle-orm";
import { db, activityTable, appointmentsTable, departmentsTable, doctorsTable, patientsTable } from "@workspace/db";
import {
  CreateAppointmentBody,
  CreateAppointmentResponse,
  CreatePatientBody,
  CreatePatientResponse,
  GetDashboardSummaryResponse,
  GetPatientParams,
  GetPatientResponse,
  ListActivityQueryParams,
  ListActivityResponse,
  ListAppointmentsQueryParams,
  ListAppointmentsResponse,
  ListDepartmentsResponse,
  ListDoctorsQueryParams,
  ListDoctorsResponse,
  ListPatientsQueryParams,
  ListPatientsResponse,
  UpdateAppointmentBody,
  UpdateAppointmentParams,
  UpdateAppointmentResponse,
} from "@workspace/api-zod";
import { dateOnly, getAppointmentRow, insertActivity, listAppointmentRows } from "../lib/hospital-data";

const router: IRouter = Router();

function queryString(value: unknown) {
  return typeof value === "string" ? value : undefined;
}

function toQueryDate(value: unknown) {
  const raw = queryString(value);
  return raw ? new Date(`${raw}T00:00:00.000Z`) : undefined;
}

function appointmentDate(value: unknown) {
  return value instanceof Date ? dateOnly(value) : String(value);
}

router.get("/dashboard/summary", async (req, res): Promise<void> => {
  const date = queryString(req.query.date) ?? dateOnly(new Date());
  const appointments = await listAppointmentRows({ date });
  const departments = await db.select().from(departmentsTable).where(eq(departmentsTable.active, true)).orderBy(asc(departmentsTable.id));
  const nextAppointment = appointments.find((item) => ["scheduled", "confirmed", "waiting"].includes(item.status)) ?? null;
  const summary = {
    date,
    totalAppointments: appointments.length,
    completedAppointments: appointments.filter((item) => item.status === "completed").length,
    waitingPatients: appointments.filter((item) => item.status === "waiting").length,
    inProgressAppointments: appointments.filter((item) => item.status === "in_progress").length,
    cancelledAppointments: appointments.filter((item) => item.status === "cancelled").length,
    averageWaitMinutes: 18,
    departments: departments.map((department) => {
      const items = appointments.filter((item) => item.departmentId === department.id);
      return {
        departmentId: department.id,
        departmentName: department.name,
        total: items.length,
        completed: items.filter((item) => item.status === "completed").length,
        waiting: items.filter((item) => item.status === "waiting").length,
      };
    }),
    nextAppointment,
  };
  res.json(GetDashboardSummaryResponse.parse(summary));
});

router.get("/appointments", async (req, res): Promise<void> => {
  const parsed = ListAppointmentsQueryParams.safeParse({
    date: toQueryDate(req.query.date),
    status: queryString(req.query.status),
    departmentId: queryString(req.query.departmentId),
    search: queryString(req.query.search),
  });
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const rows = await listAppointmentRows({
    date: parsed.data.date ? dateOnly(parsed.data.date) : undefined,
    status: parsed.data.status,
    departmentId: parsed.data.departmentId,
    search: parsed.data.search,
  });
  res.json(ListAppointmentsResponse.parse(rows));
});

router.post("/appointments", async (req, res): Promise<void> => {
  const parsed = CreateAppointmentBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const [created] = await db.insert(appointmentsTable).values({
    patientId: parsed.data.patientId,
    departmentId: parsed.data.departmentId,
    doctorId: parsed.data.doctorId,
    date: dateOnly(parsed.data.date),
    time: parsed.data.time,
    type: parsed.data.type,
    notes: parsed.data.notes,
  }).returning();
  const appointment = await getAppointmentRow(created.id);
  if (!appointment) {
    res.status(500).json({ error: "Unable to load created appointment" });
    return;
  }
  await insertActivity("appointment_created", `Consulta marcada para ${appointment.patientName}`);
  res.status(201).json(CreateAppointmentResponse.parse(appointment));
});

router.patch("/appointments/:id", async (req, res): Promise<void> => {
  const params = UpdateAppointmentParams.safeParse(req.params);
  const parsed = UpdateAppointmentBody.safeParse(req.body);
  if (!params.success) {
    res.status(400).json({ error: params.error.message });
    return;
  }
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const update = {
    ...(parsed.data.date ? { date: dateOnly(parsed.data.date) } : {}),
    ...(parsed.data.time !== undefined ? { time: parsed.data.time } : {}),
    ...(parsed.data.doctorId !== undefined ? { doctorId: parsed.data.doctorId } : {}),
    ...(parsed.data.status !== undefined ? { status: parsed.data.status } : {}),
    ...(parsed.data.notes !== undefined ? { notes: parsed.data.notes } : {}),
  };
  const [updated] = await db.update(appointmentsTable).set(update).where(eq(appointmentsTable.id, params.data.id)).returning();
  if (!updated) {
    res.status(404).json({ error: "Appointment not found" });
    return;
  }
  const appointment = await getAppointmentRow(updated.id);
  if (!appointment) {
    res.status(404).json({ error: "Appointment not found" });
    return;
  }
  if (parsed.data.status) {
    const statusLabels: Record<string, string> = {
      confirmed: "confirmada",
      waiting: "na fila de espera",
      in_progress: "em atendimento",
      completed: "concluída",
      cancelled: "cancelada",
      no_show: "marcada como falta",
      scheduled: "agendada",
    };
    await insertActivity("status_updated", `Consulta de ${appointment.patientName} ${statusLabels[parsed.data.status] ?? parsed.data.status}`);
  }
  res.json(UpdateAppointmentResponse.parse(appointment));
});

router.get("/patients", async (req, res): Promise<void> => {
  const parsed = ListPatientsQueryParams.safeParse({
    search: queryString(req.query.search),
    limit: queryString(req.query.limit),
  });
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const term = parsed.data.search ? `%${parsed.data.search}%` : undefined;
  const rows = await db.select().from(patientsTable)
    .where(term ? or(ilike(patientsTable.name, term), ilike(patientsTable.medicalRecordNumber, term)) : undefined)
    .orderBy(desc(patientsTable.createdAt))
    .limit(parsed.data.limit);
  res.json(ListPatientsResponse.parse(rows));
});

router.post("/patients", async (req, res): Promise<void> => {
  const parsed = CreatePatientBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const recordNumber = `HM-${new Date().getFullYear()}-${Date.now().toString().slice(-6)}`;
  const [created] = await db.insert(patientsTable).values({
    medicalRecordNumber: recordNumber,
    name: parsed.data.name,
    sex: parsed.data.sex,
    birthDate: dateOnly(parsed.data.birthDate),
    phone: parsed.data.phone,
    neighborhood: parsed.data.neighborhood,
  }).returning();
  await insertActivity("patient_registered", `Novo paciente registado: ${created.name}`);
  res.status(201).json(CreatePatientResponse.parse(created));
});

router.get("/patients/:id", async (req, res): Promise<void> => {
  const params = GetPatientParams.safeParse(req.params);
  if (!params.success) {
    res.status(400).json({ error: params.error.message });
    return;
  }
  const [patient] = await db.select().from(patientsTable).where(eq(patientsTable.id, params.data.id));
  if (!patient) {
    res.status(404).json({ error: "Patient not found" });
    return;
  }
  const appointments = await listAppointmentRows();
  res.json(GetPatientResponse.parse({ ...patient, appointments: appointments.filter((item) => item.patientId === patient.id) }));
});

router.get("/departments", async (_req, res): Promise<void> => {
  const rows = await db.select().from(departmentsTable).where(eq(departmentsTable.active, true)).orderBy(asc(departmentsTable.name));
  res.json(ListDepartmentsResponse.parse(rows));
});

router.get("/doctors", async (req, res): Promise<void> => {
  const parsed = ListDoctorsQueryParams.safeParse({ departmentId: queryString(req.query.departmentId) });
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const rows = await db.select().from(doctorsTable)
    .where(and(eq(doctorsTable.active, true), parsed.data.departmentId ? eq(doctorsTable.departmentId, parsed.data.departmentId) : undefined))
    .orderBy(asc(doctorsTable.name));
  res.json(ListDoctorsResponse.parse(rows));
});

router.get("/activity", async (req, res): Promise<void> => {
  const parsed = ListActivityQueryParams.safeParse({ limit: queryString(req.query.limit) });
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const rows = await db.select().from(activityTable).orderBy(desc(activityTable.timestamp)).limit(parsed.data.limit);
  res.json(ListActivityResponse.parse(rows));
});

export default router;