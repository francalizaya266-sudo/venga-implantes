#!/bin/bash
# Simulacro de corte de la VPN (S2-E3, ejercicio 2), en el firewall de la sede.
grep -rhE "start_action|close_action|dpd_action|dpd_delay" /etc/swanctl/ 2>/dev/null | sed "s/^ */  /"
echo "antes: $(swanctl --list-sas | grep -c ESTABLISHED) IKE establecidas"
ping -c1 -W2 -I 10.10.1.4 10.20.1.4 >/dev/null && echo "ping a producción: ok"
t0=$(date +%s.%N); echo "corte: $(date -Is)"
swanctl --terminate --ike vega >/dev/null 2>&1
sleep 1; echo "despues del corte: $(swanctl --list-sas | grep -c ESTABLISHED) establecidas; ping: $(ping -c1 -W1 -I 10.10.1.4 10.20.1.4 >/dev/null && echo ok || echo falla)"
for i in $(seq 1 120); do
  if swanctl --list-sas 2>/dev/null | grep -q INSTALLED && ping -c1 -W1 -I 10.10.1.4 10.20.1.4 >/dev/null; then
    t1=$(date +%s.%N); echo "recuperada sola: $(date -Is) en $(echo "$t1 - $t0" | bc) s"; exit 0; fi
  sleep 1
done
echo "no volvió sola en 120 s: se inicia a mano"; swanctl --initiate --child redes >/dev/null 2>&1
t1=$(date +%s.%N); echo "recuperada a mano: $(date -Is) en $(echo "$t1 - $t0" | bc) s"
