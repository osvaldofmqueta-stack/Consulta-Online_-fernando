import { integer, boolean, pgTable, serial, text } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const doctorsTable = pgTable("doctors", {
  id: serial("id").primaryKey(),
  name: text("name").notNull(),
  specialty: text("specialty").notNull(),
  departmentId: integer("department_id").notNull(),
  initials: text("initials").notNull(),
  active: boolean("active").notNull().default(true),
});

export const insertDoctorSchema = createInsertSchema(doctorsTable).omit({
  id: true,
});
export type InsertDoctor = z.infer<typeof insertDoctorSchema>;
export type Doctor = typeof doctorsTable.$inferSelect;