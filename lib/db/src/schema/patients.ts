import { pgTable, serial, text, timestamp } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const patientsTable = pgTable("patients", {
  id: serial("id").primaryKey(),
  medicalRecordNumber: text("medical_record_number").notNull().unique(),
  name: text("name").notNull(),
  sex: text("sex").notNull(),
  birthDate: text("birth_date").notNull(),
  phone: text("phone").notNull(),
  neighborhood: text("neighborhood"),
  createdAt: timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
});

export const insertPatientSchema = createInsertSchema(patientsTable).omit({
  id: true,
  medicalRecordNumber: true,
  createdAt: true,
});
export type InsertPatient = z.infer<typeof insertPatientSchema>;
export type Patient = typeof patientsTable.$inferSelect;