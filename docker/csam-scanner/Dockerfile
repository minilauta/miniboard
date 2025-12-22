FROM python:3.12-slim AS build

# install buildtime pkgs
RUN apt-get update && apt-get -y install build-essential

# create venv and install pip deps there
COPY microservices/csam-scanner/requirements.txt .
RUN python -m venv /opt/venv && \
    . /opt/venv/bin/activate && \
    pip install --no-cache-dir --no-input -r requirements.txt && \
    pip install --no-cache-dir uvicorn

FROM python:3.12-slim

# install runtime pkgs
RUN apt-get update && apt-get -y install libgl1 libglib2.0-0

# copy venv and activate it
COPY --from=build /opt/venv /opt/venv
ENV PATH="$PATH:/opt/venv/bin"

# copy app files
RUN mkdir -p /app && mkdir -p /app/tmp
WORKDIR /app
COPY microservices/csam-scanner/csam_scanner /app/csam_scanner/
COPY microservices/csam-scanner/tests /app/tests/

EXPOSE 8000

CMD [ "uvicorn", "--host", "0.0.0.0", "--port", "8000", "csam_scanner.main:app" ]
