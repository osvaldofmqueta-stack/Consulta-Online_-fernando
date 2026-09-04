import { Router, type IRouter } from "express";
import healthRouter from "./health";
import hospitalRouter from "./hospital";
import authRouter from "./auth";

const router: IRouter = Router();

router.use(healthRouter);
router.use(authRouter);
router.use(hospitalRouter);

export default router;
