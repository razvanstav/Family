import path from "node:path";
import ts from "typescript";

export function dependencyViolation(source, target) {
  const sourceParts = source.replaceAll("\\", "/").split("/");
  const targetParts = target.replaceAll("\\", "/").split("/");
  if (
    sourceParts[0] === "shared" &&
    ["app", "modules"].includes(targetParts[0])
  )
    return "shared depends on an application or module";
  if (sourceParts[0] !== "modules") return null;
  if (targetParts[0] === "app")
    return "module depends on application composition";
  if (targetParts[0] === "modules" && sourceParts[1] !== targetParts[1])
    return "module depends on another module";
  return null;
}

export function moduleSpecifiers(source) {
  const file = ts.createSourceFile(
    "source.tsx",
    source,
    ts.ScriptTarget.Latest,
    true,
  );
  const specifiers = [];
  function visit(node) {
    if (
      (ts.isImportDeclaration(node) || ts.isExportDeclaration(node)) &&
      node.moduleSpecifier &&
      ts.isStringLiteral(node.moduleSpecifier)
    )
      specifiers.push(node.moduleSpecifier.text);
    if (
      ts.isCallExpression(node) &&
      (node.expression.kind === ts.SyntaxKind.ImportKeyword ||
        (ts.isIdentifier(node.expression) &&
          node.expression.text === "require")) &&
      node.arguments.length === 1 &&
      ts.isStringLiteral(node.arguments[0])
    )
      specifiers.push(node.arguments[0].text);
    if (ts.isImportTypeNode(node) && ts.isLiteralTypeNode(node.argument)) {
      const literal = node.argument.literal;
      if (ts.isStringLiteral(literal)) specifiers.push(literal.text);
    }
    ts.forEachChild(node, visit);
  }
  visit(file);
  return specifiers;
}

export function resolveDependency(specifier, source, options) {
  const resolved = ts.resolveModuleName(specifier, source, options, ts.sys)
    .resolvedModule?.resolvedFileName;
  return (
    resolved ??
    (specifier.startsWith(".")
      ? path.resolve(path.dirname(source), specifier)
      : null)
  );
}
