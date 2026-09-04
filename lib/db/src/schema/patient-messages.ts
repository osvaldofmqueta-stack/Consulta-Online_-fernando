import { integer, pgTable, serial, text, timestamp } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const patientMessagesTable = pgTable("patient_messages", {
  id: serial("id").primaryKey(),
  patientId: integer("patient_id").notNull(),
  doctorId: integer("doctor_id"),
  senderClerkUserId: text("sender_clerk_user_id").notNull(),
  senderRole: text("sender_role").notNull(),
  body: text("body").notNull(),
  createdAt: timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
  readAt: timestamp("read_at", { withTimezone: true }),
});

export const insertPatientMessageSchema = createInsertSchema(patientMessagesTable).omit({
  id: true,
  createdAt: true,
  readAt: true,
});
export type InsertPatientMessage = z.infer<typeof insertPatientMessageSchema>;
export type PatientMessage = typeof patientMessagesTable.$inferSelect;