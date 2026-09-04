import { db, activityTable, appointmentsTable, departmentsTable, doctorsTable, patientsTable } from "@workspace/db";
import { asc, count } from "drizzle-orm";
import { logger } from "./logger";

export async function ensureSeedData() {
  const [{ total }] = await db.select({ total: count() }).from(departmentsTable);
  if (Number(total) > 0) return;

  const departments = await db.insert(departmentsTable).values([
    { name: "Medicina Geral", color: "#1b7f79", active: true },
    { name: "Pediatria", color: "#d97745", active: true },
    { name: "Cardiologia", color: "#6d5bd0", active: true },
    { name: "Ginecologia", color: "#c34f78", active: true },
  ]).returning();

  const doctors = await db.insert(doctorsTable).values([
    { name: "Dra. Ana Domingos", specialty: "Medicina interna", departmentId: departments[0].id, initials: "AD", active: true },
    { name: "Dr. Mateus António", specialty: "Pediatria", departmentId: departments[1].id, initials: "MA", active: true },
    { name: "Dr. João Sebastião", specialty: "Cardiologia", departmentId: departments[2].id, initials: "JS", active: true },
    { name: "Dra. Isabel Manuel", specialty: "Ginecologia e obstetrícia", departmentId: departments[3].id, initials: "IM", active: true },
  ]).returning();

  const patients = await db.insert(patientsTable).values([
    { medicalRecordNumber: "HM-2026-00142", name: "Maria de Fátima José", sex: "female", birthDate: "1989-04-17", phone: "923 441 802", neighborhood: "Catepa" },
    { medicalRecordNumber: "HM-2026-00141", name: "António Manuel Domingos", sex: "male", birthDate: "1974-11-03", phone: "924 108 557", neighborhood: "Maxinde" },
    { medicalRecordNumber: "HM-2026-00140", name: "Catarina Pedro Sebastião", sex: "female", birthDate: "2018-08-29", phone: "925 880 219", neighborhood: "Kalandula" },
    { medicalRecordNumber: "HM-2026-00139", name: "Paulo Afonso Miguel", sex: "male", birthDate: "1962-02-11", phone: "922 674 130", neighborhood: "Bairro Vila Matilde" },
    { medicalRecordNumber: "HM-2026-00138", name: "Teresa Joaquim António", sex: "female", birthDate: "1996-06-21", phone: "926 551 604", neighborhood: "Kiwaba Nzoji" },
  ]).returning();

  const today = new Date().toISOString().slice(0, 10);
  await db.insert(appointmentsTable).values([
    { patientId: patients[0].id, departmentId: departments[0].id, doctorId: doctors[0].id, date: today, time: "08:30", status: "completed", type: "follow_up", notes: "Revisão de tensão arterial" },
    { patientId: patients[1].id, departmentId: departments[2].id, doctorId: doctors[2].id, date: today, time: "09:00", status: "in_progress", type: "follow_up", notes: "Avaliação de rotina" },
    { patientId: patients[2].id, departmentId: departments[1].id, doctorId: doctors[1].id, date: today, time: "09:30", status: "waiting", type: "first_visit", notes: null },
    { patientId: patients[3].id, departmentId: departments[0].id, doctorId: doctors[0].id, date: today, time: "10:00", status: "confirmed", type: "return", notes: null },
    { patientId: patients[4].id, departmentId: departments[3].id, doctorId: doctors[3].id, date: today, time: "10:30", status: "scheduled", type: "first_visit", notes: "Primeira consulta" },
  ]);

  await db.insert(activityTable).values([
    { type: "appointment_completed", message: "Consulta de Maria de Fátima concluída", actor: "Dra. Ana Domingos" },
    { type: "status_updated", message: "António Manuel está em atendimento", actor: "Receção" },
    { type: "patient_registered", message: "Catarina Pedro adicionada ao sistema", actor: "Enfermagem" },
  ]);
  logger.info("Hospital seed data created");
}