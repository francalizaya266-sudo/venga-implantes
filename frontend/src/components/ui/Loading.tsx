/** El hueco de una página mientras llegan sus datos: reserva el alto para que el pie no salte. */
export function Loading() {
  return <div className="page-loading" aria-busy="true" />;
}
