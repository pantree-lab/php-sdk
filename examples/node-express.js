/**
 * Pantree + Express example
 *
 * Run:
 *   PANTREE_DSN=https://key:secret@your-pantree.com/api/ingest node node-express.js
 */

import express from "express";
import Pantree from "@pantree/js";

// Initialise once at startup
Pantree.init({
    dsn:             process.env.PANTREE_DSN,
    environment:     process.env.NODE_ENV ?? "production",
    release:         process.env.npm_package_version,
    healthReporting: true,
});

const app = express();
app.use(express.json());

// Example route
app.get("/", (_req, res) => {
    res.json({ status: "ok" });
});

// Simulate an error
app.get("/crash", (_req, _res) => {
    throw new Error("Intentional crash");
});

// Global Express error handler — capture all unhandled errors
app.use(async (err, req, res, _next) => {
    await Pantree.captureException(err, {
        context: { path: req.path, method: req.method },
    });
    res.status(500).json({ error: "Internal server error" });
});

// Graceful shutdown
const server = app.listen(3000, () => console.log("Listening on :3000"));
process.on("SIGTERM", () => {
    Pantree.stopHealthReporter();
    server.close();
});
