import type { ReactNode } from "react";
import "./pageBackdrop.css";

const images = {
  home: new URL("../assets/home/warm-countryside.png", import.meta.url).href,
  family: new URL("../assets/categories/home.webp", import.meta.url).href,
  kitchen: new URL("../assets/headers/header-kitchen.png", import.meta.url)
    .href,
  memory: new URL("../assets/categories/memories.webp", import.meta.url).href,
  calendar: new URL("../assets/categories/personal.webp", import.meta.url).href,
  vehicles: new URL("../assets/headers/header-vehicles.png", import.meta.url)
    .href,
};

export function PageBackdrop({
  image,
  className = "",
  children,
}: {
  image: keyof typeof images;
  className?: string;
  children: ReactNode;
}) {
  return (
    <div
      className={`page-backdrop page-backdrop--${image} ${className}`.trim()}
    >
      <img
        className="page-backdrop-image"
        src={images[image]}
        alt=""
        aria-hidden="true"
        decoding="async"
        draggable={false}
      />
      {children}
    </div>
  );
}
