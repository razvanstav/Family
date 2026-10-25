import { forwardRef, type InputHTMLAttributes } from "react";
import "./feedback.css";

// Adapted from EasyUI LockInput (MIT): https://easyui.site/components/lock-input
export const FocusInput = forwardRef<
  HTMLInputElement,
  InputHTMLAttributes<HTMLInputElement>
>(function FocusInput({ className = "", ...props }, ref) {
  return (
    <input {...props} ref={ref} className={`focus-input ${className}`.trim()} />
  );
});
