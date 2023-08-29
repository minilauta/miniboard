FROM python:3.11-slim

RUN apt-get update && apt-get -y install build-essential libgl1 libglib2.0-0

# install service
RUN mkdir -p /app && mkdir -p /app/tmp
COPY microservices/csam-scanner/requirements.txt /app/
WORKDIR /app
RUN pip install --no-input -r requirements.txt && \
    pip install uvicorn
COPY microservices/csam-scanner/csam_scanner /app/csam_scanner/

EXPOSE 8000

CMD [ "uvicorn", "--host", "0.0.0.0", "--port", "8000", "csam_scanner.main:app" ]
