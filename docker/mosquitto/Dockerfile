FROM eclipse-mosquitto:2
COPY docker/mosquitto/mosquitto.conf /mosquitto/config/mosquitto.conf
COPY docker/mosquitto/entrypoint.sh /usr/local/bin/hydroflow-mosquitto
RUN chmod +x /usr/local/bin/hydroflow-mosquitto
ENTRYPOINT ["hydroflow-mosquitto"]
