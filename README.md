# Sistema Historias Clínicas Electronicas 🫆
Proyecto para la gestión de pacientes, citas, diagnósticos y tratamientos.
Integración con dispositivos IoT
Alerta de interacciones medicamentosas
Cumplimiento de normativas HIPAA/leyes de protección de datos

# Contextualización
## 1. Panorama del problema
 
El sector salud ha operado durante décadas con expedientes clínicos fragmentados: registros en papel, sistemas aislados por institución (islas de información) y procesos manuales para agendamiento, facturación y seguimiento de tratamientos. Esto genera consecuencias graves:
 
- **Errores médicos evitables**: la OMS estima que los eventos adversos por medicamentos (incluyendo interacciones farmacológicas no detectadas) son una de las diez principales causas de daño al paciente en el mundo.
- **Duplicidad de exámenes** y sobrecostos al sistema porque un prestador no accede al historial de otro.
- **Pérdida de continuidad asistencial** cuando el paciente se traslada de ciudad, cambia de EPS o requiere atención de urgencia lejos de su médico habitual.
- **Baja trazabilidad** en la adherencia a tratamientos crónicos (hipertensión, diabetes), donde el seguimiento con dispositivos IoT podría anticipar descompensaciones.
- **Riesgos de privacidad**: los datos de salud son de categoría especial y su filtración expone a los pacientes y a las instituciones a sanciones y demandas.
## 2. Marco normativo aplicable
 
### En Colombia
 
- **Ley 2015 de 2020**: crea la Historia Clínica Electrónica Interoperable (HCEI) y garantiza el acceso del paciente a su información respetando el Hábeas Data.
- **Resolución 866 de 2021** (MinSalud): reglamenta el conjunto de elementos de datos clínicos relevantes para la interoperabilidad.
- **Resolución 1888 de 2025**: adopta el Resumen Digital de Atención en Salud (RDA) como mecanismo para implementar la Interoperabilidad de la HCE a nivel nacional.
- **Resolución 1995 de 1999**: define contenidos mínimos, diligenciamiento, conservación y custodia de la historia clínica (sigue vigente).
- **Ley 1581 de 2012 y Decreto 1377 de 2013**: régimen general de protección de datos personales, con tratamiento reforzado para datos sensibles (salud).
- **Resolución 3100 de 2019**: habilitación de prestadores de servicios de salud.
### Estándares internacionales de referencia
 
- **HL7 FHIR** — estándar exigido de facto para interoperabilidad clínica; en Colombia la Resolución 866 requiere que los sistemas lo soporten junto con el conjunto mínimo de datos.
- **HIPAA** (EE.UU.) — referente global de privacidad y seguridad; útil como benchmark aunque no aplique legalmente en Colombia.
- **ISO 27799** — seguridad de la información en salud.
- **SNOMED CT, LOINC, CIE-10** — vocabularios clínicos controlados.
## 3. Justificación del proyecto
 
Un sistema de HCE moderno debe responder simultáneamente a tres presiones:
 
1. **Regulatoria**: la HCEI dejó de ser opcional en Colombia; los prestadores que no se alineen enfrentan riesgos de habilitación y acreditación.
2. **Clínica**: reducir errores mediante soporte a la decisión (alertas de interacciones medicamentosas, alergias, dosis) y aprovechar señales de dispositivos IoT (glucómetros, tensiómetros, oxímetros, wearables) para monitoreo remoto y alertas tempranas.
3. **Operacional**: unificar gestión de pacientes, agendamiento, diagnósticos, tratamientos y facturación en una sola plataforma con trazabilidad de auditoría.

