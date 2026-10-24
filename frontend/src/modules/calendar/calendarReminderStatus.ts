import type { ReminderDelivery } from "./calendarTypes";

const inAppStates = {
  pending: "programat",
  delivered: "disponibil",
  cancelled: "anulat",
  expired: "moment depășit",
};

const emailStates = {
  pending: "programat",
  sending: "în curs de trimitere",
  sent: "acceptat de server",
  uncertain: "trimitere neconfirmată",
  cancelled: "anulat",
  expired: "moment depășit",
};

export function reminderDeliveryStatus(
  deliveries: ReminderDelivery[],
): string[] {
  return [
    ...new Set(
      deliveries.flatMap((delivery) => {
        if (delivery.state === "pending") return ["Reminder: programat"];
        if (delivery.in_app_enabled === null)
          return [
            `Alți destinatari: ${delivery.state === "delivered" ? "procesat" : inAppStates[delivery.state]}`,
          ];
        return [
          `În aplicație: ${delivery.in_app_enabled ? inAppStates[delivery.state] : "oprit"}`,
          `Email: ${delivery.email_state === null ? "neprogramat" : emailStates[delivery.email_state]}`,
        ];
      }),
    ),
  ];
}
