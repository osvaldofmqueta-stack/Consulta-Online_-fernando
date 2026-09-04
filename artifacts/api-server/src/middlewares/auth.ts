import { getAuth } from "@clerk/express";
import { and, eq, inArray } from "drizzle-orm";
import type { NextFunction, Request, Response } from "express";
import { db, userProfilesTable } from "@workspace/db";

export type AuthenticatedRequest = Request & {
  userId: string;
};

export function requireAuth(req: Request, res: Response, next: NextFunction) {
  const auth = getAuth(req);
  const candidate = auth?.sessionClaims?.userId || auth?.userId;
  const userId = typeof candidate === "string" ? candidate : undefined;
  if (!userId) {
    res.status(401).json({ error: "Unauthorized" });
    return;
  }
  (req as AuthenticatedRequest).userId = userId;
  next();
}

export async function getUserProfile(userId: string) {
  return db.query.userProfilesTable.findFirst({
    where: eq(userProfilesTable.clerkUserId, userId),
  });
}

export async function ensureUserProfile(userId: string) {
  await db.insert(userProfilesTable).values({
    clerkUserId: userId,
    role: "patient",
  }).onConflictDoNothing({ target: userProfilesTable.clerkUserId });
  return getUserProfile(userId);
}

export function requireStaff(req: Request, res: Response, next: NextFunction) {
  const userId = (req as AuthenticatedRequest).userId;
  void getUserProfile(userId).then((profile) => {
    if (!profile || !["reception", "nursing", "doctor", "coordination"].includes(profile.role)) {
      res.status(403).json({ error: "Hospital staff access required" });
      return;
    }
    next();
  }).catch(next);
}

export async function getStaffProfile(userId: string) {
  return db.select().from(userProfilesTable).where(
    and(
      eq(userProfilesTable.clerkUserId, userId),
      inArray(userProfilesTable.role, ["reception", "nursing", "doctor", "coordination"]),
    ),
  ).limit(1).then(([profile]) => profile);
}