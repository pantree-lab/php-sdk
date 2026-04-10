/**
 * Pantree + Next.js App Router example
 *
 * 1. Create this file as `instrumentation.ts` at your project root.
 * 2. Add  `experimental: { instrumentationHook: true }`  to next.config.js
 *    (Next.js 13 / 14 — not needed in Next.js 15+).
 * 3. Set PANTREE_DSN in .env.local
 */

// instrumentation.ts
export async function register() {
    if (process.env.NEXT_RUNTIME === "nodejs") {
        const { default: Pantree } = await import("@pantree/js");

        Pantree.init({
            dsn:             process.env.PANTREE_DSN,
            environment:     process.env.NODE_ENV ?? "production",
            release:         process.env.NEXT_PUBLIC_APP_VERSION,
            healthReporting: true,
        });

        // Optional: capture unhandled promise rejections
        process.on("unhandledRejection", (reason) => {
            Pantree.captureException(
                reason instanceof Error ? reason : new Error(String(reason)),
                { context: { source: "unhandledRejection" } },
            );
        });
    }
}

// -----------------------------------------------------------------------
// In a Server Action or Route Handler:
//
// import Pantree from "@pantree/js";
//
// export async function POST(req: Request) {
//   try {
//     const data = await req.json();
//     await processOrder(data);
//     return Response.json({ ok: true });
//   } catch (err) {
//     await Pantree.captureException(err, { context: { route: "/api/orders" } });
//     return Response.json({ error: "Failed" }, { status: 500 });
//   }
// }
// -----------------------------------------------------------------------
