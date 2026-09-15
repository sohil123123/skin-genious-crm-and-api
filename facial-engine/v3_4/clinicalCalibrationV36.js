// Compatibility module: regional grade normalization only. V3.6 report bridge is retired.
const G = [0,.12,.30,.50,.73,1]
export function interpolateV36(value, xs, ys) {
  if (!Number.isFinite(value)) throw new Error('Calibration requires finite evidence')
  if (xs.length !== ys.length || xs.length < 2 || xs.some((x,i) => !Number.isFinite(x) || (i && x <= xs[i-1]))) throw new Error('Invalid calibration knots')
  if (value <= xs[0]) return ys[0]
  for (let i=1;i<xs.length;i++) if (value <= xs[i]) return ys[i-1] + (ys[i]-ys[i-1]) * (value-xs[i-1])/(xs[i]-xs[i-1])
  return ys.at(-1)
}
export function normalizeContinuousGradeV36(grade) { return interpolateV36(grade,[0,1,2,3,4,5],G) }

export function deriveCalibratedParameterV36() { throw new Error('V3.6 report calibration bridge retired. Re-run images with V3.7 legacy measurements.') }
export function deriveSkinTypeV36() { throw new Error('V3.6 skin-type bridge retired. Use legacySkinTypeV37.') }
