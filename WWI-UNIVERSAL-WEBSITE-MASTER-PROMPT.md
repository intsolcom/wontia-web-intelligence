# WWI — UNIVERSAL WEBSITE GENERATION MASTER PROMPT v1.0

> Spec maestro de generación de sitios web (estándar WONTIA). TIA lo usa como base al generar PREVIEWS y sitios reales. Principios: UNDERSTAND → DECIDE → ACT. Anti-alucinación: nunca inventar certificaciones, clientes, precios ni datos factuales — usar placeholders explícitos {{CAMPO}}. Preferir simplicidad, accesibilidad WCAG 2.2 AA, SEO, performance y arquitectura AI-native a 5 años.

## ESTRUCTURA FIJA DE PLANTILLA BÁSICA (nivel 1)
1. Header (logo + nav 4-6 items + CTA)
2. Hero (eyebrow + H1 + subtítulo + CTA primario + CTA secundario WhatsApp)
3. Propuesta de valor (3 bullets)
4. Servicios/Productos (3-6 cards)
5. Sobre nosotros
6. Beneficios (4 items)
7. Testimonios (2-3, marcados como ejemplo si no hay reales)
8. CTA final
9. Contacto ({{TELEFONO}} {{EMAIL}} {{DIRECCION}} {{WHATSAPP}})
10. Footer

## CONTRATO DE SALIDA (JSON estricto)
{
  "business_name": "...",
  "tagline": "...",
  "nav": ["Inicio","Servicios","Sobre mí","Contacto"],
  "colors": {"primary": "#hex", "secondary": "#hex"},
  "hero": {"eyebrow": "...", "title": "...", "subtitle": "..."},
  "value_prop": ["...","...","..."],
  "services": [{"title": "...", "desc": "..."}],
  "about": "...",
  "benefits": [{"title": "...", "desc": "..."}],
  "testimonials": [{"quote": "...", "author": "Cliente (ejemplo)"}],
  "cta": {"title": "...", "subtitle": "..."},
  "contact": {"phone": "{{TELEFONO}}", "email": "{{EMAIL}}", "address": "{{DIRECCION}}", "whatsapp": "{{WHATSAPP}}"},
  "footer_note": "..."
}

## REGLAS
- Colores profesionales por sector (sin gradientes excesivos).
- Español de Colombia. Copy orientado a conversión.
- Placeholders {{...}} cuando falten datos reales.
- Componentes reutilizables, estados definidos, eventos de analytics listos.