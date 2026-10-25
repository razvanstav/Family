import type { ButtonHTMLAttributes } from "react";
import { Loader2 } from "lucide-react";
import "./feedback.css";

// Adapted from EasyUI MorphingButton (MIT): https://easyui.site/components/morphing-button
export function ActionButton({
  loading,
  loadingLabel,
  disabled,
  className = "",
  children,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
  loading: boolean;
  loadingLabel: string;
}) {
  return (
    <button
      {...props}
      className={`action-button ${className}`.trim()}
      disabled={disabled || loading}
      aria-busy={loading ? true : props["aria-busy"]}
      data-loading={loading || undefined}
    >
      <span className="action-button-label" aria-hidden={loading || undefined}>
        {children}
      </span>
      {loading && (
        <span className="action-button-loading">
          <Loader2
            size={16}
            className="action-button-spinner"
            aria-hidden="true"
          />
          <span>{loadingLabel}</span>
        </span>
      )}
    </button>
  );
}
