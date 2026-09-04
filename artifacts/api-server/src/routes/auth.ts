import { Router, type IRouter } from "express";
import { and, asc, desc, eq } from "drizzle-orm";
import { db, appointmentsTable, doctorsTable, patientMessagesTable, patientsTable, userProfilesTable } from "@workspace/db";
import {
  GetAuthMeResponse,
  GetPatientPortalResponse,
  LinkPatientBody,
  LinkPatientResponse,
  ListPatientMessagesResponse,
  SendPatientMessageBody,
  SendPatientMessageResponse,
} from "@workspace/api-zod";
import { listAppointmentRows } from "../lib/hospital-data";
import { ensureUserProfile, getUserProfile, type AuthenticatedRequest, requireAuth } from "../middlewares/auth";

const router: IRouter = Router();

function profileResponse(profile: NonNullable<Awaited<ReturnType<typeof ensureUserProfile>>>) {
  return {
    id: profile.id,
    role: profile.role,
    displayName: profile.displayName,
    patientId: profile.patientId,
    doctorId: profile.doctorId,
    createdAt: profile.createdAt,
  };
}

router.get("/auth/me", requireAuth, async (req, res, next): Promise<void> => {
  try {
    const profile = await ensureUserProfile((req as AuthenticatedRequest).userId);
    if (!profile) {
      res.status(500).json({ error: "Unable to create user profile" });
      return;
    }
    res.json(GetAuthMeResponse.parse(profileResponse(profile)));
  } catch (error) {
    next(error);
  }
});

router.post("/auth/link-patient", requireAuth, async (req, res, next): Promise<void> => {
  try {
    const parsed = LinkPatientBody.safeParse(req.body);
    if (!parsed.success) {
      res.status(400).json({ error: parsed.error.message });
      return;
    }
    const [patient] = await db.select().from(patientsTable).where(and(
      eq(patientsTable.medicalRecordNumber, parsed.data.medicalRecordNumber.trim().toUpperCase()),
      eq(patientsTable.phone, parsed.data.phone.trim()),
    )).limit(1);
    if (!patient) {
      res.status(404).json({ error: "No patient record matched those details" });
      return;
    }
    const profile = await ensureUserProfile((req as AuthenticatedRequest).userId);
    if (!profile) {
      res.status(500).json({ error: "Unable to create user profile" });
      return;
    }
    const [updated] = await db.update(userProfilesTable)
      .set({ patientId: patient.id, updatedAt: new Date() })
      .where(eq(userProfilesTable.id, profile.id))
      .returning();
    res.json(LinkPatientResponse.parse(profileResponse(updated)));
  } catch (error) {
    next(error);
  }
});

router.get("/auth/patient-portal", requireAuth, async (req, res, next): Promise<void> => {
  try {
    const profile = await ensureUserProfile((req as AuthenticatedRequest).userId);
    if (!profile) {
      res.status(500).json({ error: "Unable to create user profile" });
      return;
    }
    const patient = profile.patientId
      ? (await db.select().from(patientsTable).where(eq(patientsTable.id, profile.patientId)).limit(1))[0] ?? null
      : null;
    const appointments = patient ? (await listAppointmentRows()).filter((item) => item.patientId === patient.id) : [];
    res.json(GetPatientPortalResponse.parse({
      profile: profileResponse(profile),
      patient,
      appointments,
    }));
  } catch (error) {
    next(error);
  }
});

function messageResponse(message: typeof patientMessagesTable.$inferSelect) {
  return {
    id: message.id,
    patientId: message.patientId,
    doctorId: message.doctorId,
    senderRole: message.senderRole,
    body: message.body,
    createdAt: message.createdAt,
    readAt: message.readAt,
  };
}

router.get("/auth/messages", requireAuth, async (req, res, next): Promise<void> => {
  try {
    const profile = await ensureUserProfile((req as AuthenticatedRequest).userId);
    if (!profile?.patientId) {
      res.status(403).json({ error: "A linked patient record is required" });
      return;
    }
    const messages = await db.select().from(patientMessagesTable)
      .where(eq(patientMessagesTable.patientId, profile.patientId))
      .orderBy(asc(patientMessagesTable.createdAt));
    res.json(ListPatientMessagesResponse.parse(messages.map(messageResponse)));
  } catch (error) {
    next(error);
  }
});

router.post("/auth/messages", requireAuth, async (req, res, next): Promise<void> => {
  try {
    const parsed = SendPatientMessageBody.safeParse(req.body);
    if (!parsed.success) {
      res.status(400).json({ error: parsed.error.message });
      return;
    }
    const profile = await ensureUserProfile((req as AuthenticatedRequest).userId);
    if (!profile?.patientId) {
      res.status(403).json({ error: "A linked patient record is required" });
      return;
    }
    const [latestAppointment] = await db.select({ doctorId: appointmentsTable.doctorId })
      .from(appointmentsTable)
      .where(eq(appointmentsTable.patientId, profile.patientId))
      .orderBy(desc(appointmentsTable.date), desc(appointmentsTable.time))
      .limit(1);
    const [created] = await db.insert(patientMessagesTable).values({
      patientId: profile.patientId,
      doctorId: latestAppointment?.doctorId ?? null,
      senderClerkUserId: (req as AuthenticatedRequest).userId,
      senderRole: "patient",
      body: parsed.data.body.trim(),
    }).returning();
    res.status(201).json(SendPatientMessageResponse.parse(messageResponse(created)));
  } catch (error) {
    next(error);
  }
});

export default router;