import { and, asc, desc, eq, ilike, or } from "drizzle-orm";
import { db, activityTable, appointmentsTable, departmentsTable, doctorsTable, patientsTable } from "@workspace/db";

export async function listAppointmentRows(filters?: {
  date?: string;
  status?: string;
  departmentId?: number;
  search?: string;
}) {
  const conditions = [];
  if (filters?.date) conditions.push(eq(appointmentsTable.date, filters.date));
  if (filters?.status) conditions.push(eq(appointmentsTable.status, filters.status));
  if (filters?.departmentId) conditions.push(eq(appointmentsTable.departmentId, filters.departmentId));
  if (filters?.search) {
    const term = `%${filters.search}%`;
    conditions.push(or(ilike(patientsTable.name, term), ilike(patientsTable.medicalRecordNumber, term)));
  }

  const rows = await db
    .select({
      id: appointmentsTable.id,
      patientId: appointmentsTable.patientId,
      patientName: patientsTable.name,
      medicalRecordNumber: patientsTable.medicalRecordNumber,
      departmentId: appointmentsTable.departmentId,
      departmentName: departmentsTable.name,
      doctorId: appointmentsTable.doctorId,
      doctorName: doctorsTable.name,
      date: appointmentsTable.date,
      time: appointmentsTable.time,
      status: appointmentsTable.status,
      type: appointmentsTable.type,
      notes: appointmentsTable.notes,
      createdAt: appointmentsTable.createdAt,
    })
    .from(appointmentsTable)
    .innerJoin(patientsTable, eq(appointmentsTable.patientId, patientsTable.id))
    .innerJoin(departmentsTable, eq(appointmentsTable.departmentId, departmentsTable.id))
    .innerJoin(doctorsTable, eq(appointmentsTable.doctorId, doctorsTable.id))
    .where(conditions.length ? and(...conditions) : undefined)
    .orderBy(asc(appointmentsTable.date), asc(appointmentsTable.time));

  return rows;
}

export async function getAppointmentRow(id: number) {
  const rows = await listAppointmentRows();
  return rows.find((row) => row.id === id);
}

export async function insertActivity(type: string, message: string, actor = "Sistema") {
  await db.insert(activityTable).values({ type, message, actor });
}

export function dateOnly(value: Date) {
  return value.toISOString().slice(0, 10);
}