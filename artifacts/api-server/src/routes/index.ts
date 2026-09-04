import { Router, type IRouter } from "express";
import healthRouter from "./health";
import hospitalRouter from "./hospital";

const router: IRouter = Router();

router.use(healthRouter);
router.use(hospitalRouter);

export default router;
