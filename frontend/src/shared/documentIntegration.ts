import type { ReactNode } from "react";
import type { Document } from "./api";

export type DocumentCreationRender = (props: {
  onClose: () => void;
  onSaved: (document: Document) => Promise<void>;
}) => ReactNode;

export type DocumentDetailRender = (props: {
  id: string;
  onClose: () => void;
  onChanged: () => void;
}) => ReactNode;
