import type { ReactNode } from "react";
import "./feedback.css";

// Adapted from EasyUI Alert (MIT): https://easyui.site/components/alert
export function FeedbackMessage({
  variant,
  children,
}: {
  variant: "error" | "notice";
  children: ReactNode;
}) {
  return (
    <p
      className={variant}
      role={variant === "error" ? "alert" : "status"}
      data-feedback-message=""
    >
      {children}
    </p>
  );
}
